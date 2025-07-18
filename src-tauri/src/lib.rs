use std::sync::{Arc, Mutex, MutexGuard};

use tauri::{App, AppHandle, Manager, RunEvent, State};
use tauri_plugin_shell::{
    process::{Command, CommandChild},
    ShellExt,
};

struct LaravelServer(pub Arc<Mutex<Option<CommandChild>>>);

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let app: App = tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_sql::Builder::new().build())
        .plugin(tauri_plugin_single_instance::init(
            |app: &AppHandle, _args, _cwd| {
                let _ = app
                    .get_webview_window("app")
                    .expect("no main window")
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
        .invoke_handler(tauri::generate_handler![set_complete])
        .build(tauri::generate_context!())
        .expect("error while running tauri application");

    app.run(|handler: &AppHandle, event: RunEvent| match event {
        RunEvent::Ready => {
            handler.manage(LaravelServer(Arc::new(Mutex::new(Some(start_laravel_server(handler))))));
        }
        RunEvent::ExitRequested { .. } | RunEvent::Exit => {
            let laravel_server_mutex: State<'_, LaravelServer> = handler.try_state::<LaravelServer>().expect("Fail getting server instance");
            let mut laravel_server_guard: MutexGuard<'_, Option<CommandChild>> = laravel_server_mutex.0.lock().expect("Fail getting server instance");
            let laravel_server_process: CommandChild = laravel_server_guard.take().expect("Failure getting server process");
            laravel_server_process.kill().expect("Fail killing laravel server");
        }
        _ => {}
    });
}

#[tauri::command]
async fn set_complete(app: AppHandle) -> Result<(), ()> {
    let splash_window = app.get_webview_window("splashscreen").unwrap();
    let main_window = app.get_webview_window("app").unwrap();

    main_window.show().unwrap();
    splash_window.close().unwrap();

    Ok(())
}

fn start_laravel_server(handler: &AppHandle) -> CommandChild {
    let php: Command = handler.shell().sidecar("php").unwrap();
    let (mut _receiver, child) = php
        .args([
            "-S",
            "127.0.0.1:8000",
            "../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php",
        ])
        .current_dir("./resources/app/public")
        .spawn()
        .expect("Fallo al iniciar el servidor de PHP");

    println!("Server Started in port 8000");

    return child;
}
