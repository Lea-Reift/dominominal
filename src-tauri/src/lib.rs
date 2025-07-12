use std::{
    net::TcpStream,
    os::windows::process::CommandExt,
    process::{Command, Stdio},
    sync::{Arc, Mutex},
    thread,
    time::Duration,
};

use tauri::{Manager, WindowEvent};

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let process = Arc::new(Mutex::new(None));

    if TcpStream::connect("127.0.0.1:8000").is_err() {
        let child = Command::new("./resources/php/php.exe")
            .args([
                "-S",
                "127.0.0.1:8000",
                "../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php",
            ])
            .current_dir("./resources/app/public")
            .stdout(Stdio::null())
            .stderr(Stdio::null())
            .creation_flags(0x08000000) // HIDE PHP WINDOW
            .spawn()
            .expect("Fallo al iniciar el servidor de PHP");

        let mut lock = process.lock().unwrap();
        *lock = Some(child.id());

        while TcpStream::connect("127.0.0.1:8000").is_err() {
            thread::sleep(Duration::from_millis(300));
        }
    }

    let cloned_process = process.clone();

    tauri::Builder::default()
        .plugin(tauri_plugin_single_instance::init(|app, _args, _cwd| {
            let _ = app.get_webview_window("main")
                       .expect("no main window")
                       .set_focus();
        }))
        .setup(|app| {
            if cfg!(debug_assertions) {
                app.handle().plugin(
                    tauri_plugin_log::Builder::default()
                        .level(log::LevelFilter::Info)
                        .build(),
                )?;
            }

            app.get_webview_window("main").unwrap().maximize().unwrap();

            Ok(())
        })
        .on_window_event(move |_, event| match event {
            WindowEvent::CloseRequested { .. } => {
                if let Some(pid) = cloned_process.lock().unwrap().as_mut() {
                    Command::new("taskkill")
                        .args(["/PID", &pid.to_string(), "/F"])
                        .status()
                        .expect("Fallo al ejecutar taskkill");
                }
            }
            _ => {}
        })
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
