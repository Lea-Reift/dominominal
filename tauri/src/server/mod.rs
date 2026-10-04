use std::{fs, path::PathBuf, sync::Mutex};
use tauri::{Manager, State};
use tauri::path::BaseDirectory::Resource;
use tauri_plugin_shell::process::{CommandChild, CommandEvent};
use crate::window::set_complete;

#[derive(Default)]
pub struct LaravelInformation {
    pub server: Option<CommandChild>,
    pub database_path: Option<PathBuf>,
}

pub fn kill_laravel_server() {
    let laravel_state: State<'_, Mutex<LaravelInformation>> = laravel_state();
    let mut laravel_information = laravel_state.lock().expect("Failure getting information");

    if let Some(laravel_server_process) = laravel_information.server.take() {
        match laravel_server_process.kill() {
            Ok(_) => println!("Laravel server terminated successfully"),
            Err(e) => eprintln!("Failed to kill Laravel server: {}", e),
        }
    }

    drop(laravel_information);
}

pub fn start_laravel_server(database_path: &PathBuf) -> CommandChild {
    let handler = crate::global::get_app_handle();
    let ephpm_config_path = handler
        .path()
        .resource_dir()
        .expect("Fail getting path")
        .join("ephpm.toml");

    let realpath: PathBuf = handler
        .path()
        .resolve("resources/app", Resource)
        .expect("Fail getting route");

    let database_real_path = dunce::canonicalize(database_path).expect("failed");
    let database_real_path_string = database_real_path.to_str().expect("failed");
    let justix_real_path = dunce::canonicalize(realpath).expect("failed");
    let justix_real_path_string = justix_real_path.to_str().unwrap_or("failed");
    
    let toml = format!("\
    [server]
    listen = \"0.0.0.0:8000\"
    document_root ='{justix_real_path_string}'
    index_files = [\"index.php\"]

    # Laravel routes through public/index.php for any URL it doesn't have a static asset for
    fallback = [\"$uri\", \"$uri/\", \"/index.php?$query_string\"]

    [php]
    mode=\"worker\"
    memory_limit = \"256M\"
    max_execution_time = 30
    ini_overrides = [
        [\"display_errors\", \"Off\"],
        [\"error_reporting\", \"E_ALL\"],
    ]

    [php.worker]
    script = \"vendor/bin/ephpm-octane-worker\"

    [db.sqlite]
    path = '{database_real_path_string}'
    ");

    fs::write(ephpm_config_path.clone(), toml).expect("TODO: panic message");

    let (mut receiver, child) = crate::commands::run_ephpm_command(
        ["serve", "-c", ephpm_config_path.to_str().expect("crashed")].to_vec(),
        database_path
    );

    tauri::async_runtime::spawn(async move {
        while let Some(event) = receiver.recv().await {
            if let CommandEvent::Stderr(line_bytes) = event.clone() {
                let line = String::from_utf8_lossy(&line_bytes);
                println!("{}", line);
            }
            if let CommandEvent::Stdout(line_bytes) = event.clone() {
                let line = String::from_utf8_lossy(&line_bytes);
                println!("{}", line);
            }
        }
    });

    // Wait for main page to be ready before showing window
    tauri::async_runtime::spawn(async move {
        tokio::time::sleep(tokio::time::Duration::from_millis(2000)).await;

        // Check if main page is accessible
        loop {
            let client = reqwest::Client::builder()
                .timeout(tokio::time::Duration::from_secs(3))
                .build()
                .expect("Failed to create HTTP client");
            let mut request = client.get("http://127.0.0.1:8000");

            // Add stored cookies if available
            if let Some(cookies) = crate::window::get_stored_cookies() {
                request = request.header("Cookie", cookies);
            }

            if let Ok(response) = request.send().await {
                if response.status().is_success() {
                    break;
                }
            }
            tokio::time::sleep(tokio::time::Duration::from_millis(500)).await;
        }

        let _ = set_complete().await;
    });

    child
}

pub fn laravel_state() -> State<'static, Mutex<LaravelInformation>> {
    let handler = crate::global::get_app_handle();
    handler
        .try_state::<Mutex<LaravelInformation>>()
        .expect("State not found")
}
