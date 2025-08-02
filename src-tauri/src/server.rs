use std::{
    path::PathBuf, sync::Mutex
};
use tauri::{Manager, State};
use tauri_plugin_shell::{
    process::{CommandChild, CommandEvent},
};

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
        laravel_server_process
            .kill()
            .expect("Fail killing laravel server");
    }

    drop(laravel_information);
}

pub fn start_laravel_server(database_path: &PathBuf) -> CommandChild {
    let handler = crate::global::get_app_handle();
    let resources_path = handler
        .path()
        .resource_dir()
        .expect("Fail getting path")
        .join("resources/app/public");

    let (mut receiver, child) = crate::commands::run_php_command(
        [
            "-S", "127.0.0.1:8000",
        ].to_vec(),
        Some(
            resources_path
                .canonicalize()
                .expect("Failure canonizing app"),
        ),
        database_path,
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
            if let Ok(response) = reqwest::get("http://127.0.0.1:8000/main").await {
                if response.status().is_success() {
                    break;
                }
            }
            tokio::time::sleep(tokio::time::Duration::from_millis(500)).await;
        }
        
        let _ = set_complete().await;
    });

    return child;
}

pub fn laravel_state() -> State<'static, Mutex<LaravelInformation>> {
    let handler = crate::global::get_app_handle();
    let state: State<'_, Mutex<LaravelInformation>> = handler
        .try_state::<Mutex<LaravelInformation>>()
        .expect("State not found");
    return state;
}