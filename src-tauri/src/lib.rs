use std::{
    path::PathBuf,
    sync::{Mutex, MutexGuard},
};

use tauri::{
    App, AppHandle, Manager, RunEvent, State,
};

mod commands;
mod database;
mod global;
mod server;
mod updater;
mod window;

use server::LaravelInformation;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let app: App = tauri::Builder::default()
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_updater::Builder::new().build())
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_single_instance::init(
            |app: &AppHandle, _args, _cwd| {
                let _ = app
                    .get_webview_window("app")
                    .expect("no app window")
                    .set_focus();
            },
        ))
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }
            Ok(())
        })
        .invoke_handler(tauri::generate_handler![window::set_complete, window::reset_retry_count, window::start_window_load_monitoring])
        .build(tauri::generate_context!())
        .expect("error while running tauri application");

    let database_path: std::path::PathBuf = app
        .handle()
        .path()
        .app_local_data_dir()
        .expect("Fail getting path")
        .join("dominominal.sqlite");

    let laravel_information: LaravelInformation = LaravelInformation {
        database_path: Some(database_path),
        server: None,
    };

    app.handle().manage(Mutex::new(laravel_information));
    
    // Initialize global app handle
    global::init_app_handle(app.handle().clone());

    app.run(|handler: &AppHandle, event: RunEvent| match event {
        RunEvent::Ready => {
            let laravel_state: State<'_, Mutex<LaravelInformation>> = server::laravel_state();
            let laravel_information: MutexGuard<'_, LaravelInformation> =
                laravel_state.lock().expect("Failure getting information");
            let database_path: PathBuf = laravel_information.database_path.clone().expect("Missing database path");
            drop(laravel_information);
            
            database::prepare_database(&database_path);

            let storage_path: std::path::PathBuf = handler
                .path()
                .resource_dir()
                .expect("Fail getting path")
                .join("resources/app/storage");

            if !std::fs::exists(storage_path).unwrap_or(false) {
                // Run comprehensive optimization commands
                let optimization_commands = vec![
                    ["config:cache"],
                    ["route:cache"],
                    ["view:cache"],
                    ["optimize"],
                    ["filament:optimize"],
                ];

                for cmd in optimization_commands {
                    let (mut receiver, _) = commands::run_artisan_command(cmd.to_vec(), &database_path);
                    tauri::async_runtime::block_on(async move {
                        println!("Running artisan {}...", cmd.join(" "));
                        receiver.recv().await;
                        println!("Artisan {} done!", cmd.join(" "));
                    });
                }
            }

            let laravel_server: Option<tauri_plugin_shell::process::CommandChild> = Some(server::start_laravel_server(&database_path));

            let laravel_state: State<'_, Mutex<LaravelInformation>> = server::laravel_state();
            let mut laravel_information: MutexGuard<'_, LaravelInformation> =
                laravel_state.lock().expect("Failure getting information");
            laravel_information.server = laravel_server;
            drop(laravel_information);
        }

        RunEvent::ExitRequested { .. } | RunEvent::Exit => {
            server::kill_laravel_server();
        }
        _ => {}
    });
}

