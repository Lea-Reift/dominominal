use std::{
    path::PathBuf,
    sync::{Mutex, MutexGuard},
};
use tauri::{AppHandle, Manager, State};
use tauri_plugin_shell::{
    process::{CommandChild, CommandEvent},
};

#[derive(Default)]
pub struct LaravelInformation {
    pub server: Option<CommandChild>,
    pub database_path: Option<PathBuf>,
}

pub fn kill_laravel_server(handler: &AppHandle) {
    let laravel_state: State<'_, Mutex<LaravelInformation>> = laravel_state(handler);
    let mut laravel_information = laravel_state.lock().expect("Failure getting information");

    if let Some(laravel_server_process) = laravel_information.server.take() {
        laravel_server_process
            .kill()
            .expect("Fail killing laravel server");
    }

    drop(laravel_information);
}

pub fn start_laravel_server(handler: &AppHandle, database_path: &PathBuf) -> CommandChild {
    let resources_path = handler
        .path()
        .resource_dir()
        .expect("Fail getting path")
        .join("resources/app/public");

    let (mut receiver, child) = crate::commands::run_php_command(
        handler,
        ["-S", "127.0.0.1:8000"].to_vec(),
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
    return child;
}

pub fn laravel_state(handler: &AppHandle) -> State<'_, Mutex<LaravelInformation>> {
    let state: State<'_, Mutex<LaravelInformation>> = handler
        .try_state::<Mutex<LaravelInformation>>()
        .expect("State not found");
    return state;
}