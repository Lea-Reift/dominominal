use std::{
    path::PathBuf,
    sync::{Arc, Mutex, MutexGuard},
};

use tauri::{
    async_runtime::Receiver, path::BaseDirectory, window::ProgressBarState, App, AppHandle,
    Manager, RunEvent, State,
};
use tauri_plugin_shell::{
    process::{Command, CommandChild, CommandEvent},
    ShellExt,
};

use tauri_plugin_dialog::{DialogExt, MessageDialogButtons, MessageDialogKind};

use tauri_plugin_updater::UpdaterExt;

struct LaravelServer(pub Arc<Mutex<Option<CommandChild>>>);

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
        .invoke_handler(tauri::generate_handler![set_complete])
        .build(tauri::generate_context!())
        .expect("error while running tauri application");

    app.run(|handler: &AppHandle, event: RunEvent| match event {
        RunEvent::Ready => {
            let storage_path: std::path::PathBuf = handler
                .path()
                .resource_dir()
                .expect("Fail getting path")
                .join("resources/app/storage");

            if !std::fs::exists(storage_path).unwrap_or(false) {
                let (mut receiver, _) =
                    run_php_command(handler, ["artisan", "optimize"].to_vec(), None);

                tauri::async_runtime::block_on(async move {
                    println!("Running artisan optimize...");
                    receiver.recv().await;
                    println!("Artisan optimize done!");
                });

                let (mut receiver, _) =
                    run_php_command(handler, ["artisan", "filament:optimize"].to_vec(), None);

                tauri::async_runtime::block_on(async move {
                    println!("Running artisan filament:optimize...");
                    receiver.recv().await;
                    println!("Artisan filament:optimize done!");
                });
            }

            handler.manage(LaravelServer(Arc::new(Mutex::new(Some(
                start_laravel_server(handler),
            )))));
        }

        RunEvent::ExitRequested { .. } | RunEvent::Exit => {
            kill_laravel_server(handler);
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

    let handle = app.clone();

    tauri::async_runtime::spawn(async move {
        update(handle).await.expect("error updating app");
    });
    Ok(())
}

fn kill_laravel_server(handler: &AppHandle) {
    let laravel_server_mutex: State<'_, LaravelServer> = handler
        .try_state::<LaravelServer>()
        .expect("Fail getting server instance");

    let mut laravel_server_guard: MutexGuard<'_, Option<CommandChild>> = laravel_server_mutex
        .0
        .lock()
        .expect("Fail getting server instance");
    let laravel_server_process: CommandChild = laravel_server_guard
        .take()
        .expect("Failure getting server process");
    laravel_server_process
        .kill()
        .expect("Fail killing laravel server");
}

fn start_laravel_server(handler: &AppHandle) -> CommandChild {
    let resources_path = handler
        .path()
        .resource_dir()
        .expect("Fail getting path")
        .join("resources/app/public");

    let (mut receiver, child) = handler
        .shell()
        .sidecar("php")
        .expect("Fail getting php sidecar")
        .current_dir(
            resources_path
                .canonicalize()
                .expect("Failure canonizing app"),
        )
        .args(["-S", "127.0.0.1:8000"])
        .spawn()
        .expect("Failure starting server");

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

    println!("Server Started in port 8000");
    return child;
}

fn run_php_command(
    handler: &AppHandle,
    args: Vec<&str>,
    directory: Option<&str>,
) -> (Receiver<CommandEvent>, CommandChild) {
    let php: Command = handler.shell().sidecar("php").unwrap();
    let command_directory = match directory {
        None => "./resources/app",
        _ => directory.unwrap(),
    };

    let realpath: PathBuf = handler
        .path()
        .resolve(command_directory, BaseDirectory::Resource)
        .expect("Fail getting route");

    return php
        .args(args.clone())
        .current_dir(realpath.to_str().expect("Failure getting path"))
        .spawn()
        .expect(&format!("Failure running php command: {:?}", args));
}

async fn update(app: AppHandle) -> tauri_plugin_updater::Result<()> {
    let app_clone = app.clone();

    let updater = app
        .updater_builder()
        .version_comparator(|_current, _update| {
            return true;
        })
        .on_before_exit(move || {
            app_clone
                .dialog()
                .message("Se va a instalar la actualización. La app se va a cerrar")
                .kind(MessageDialogKind::Warning)
                .title("Instalando Actualización")
                .blocking_show();
            kill_laravel_server(&app_clone);
        })
        .build()?;

    if let Some(update) = updater.check().await? {
        let answer = app
            .dialog()
            .message("Hay una nueva actualización disponible. ¿Desea instalarla ahora?")
            .title("Actualización Disponible")
            .blocking_show();

        if !answer {
            return Ok(());
        }

        let mut downloaded: u64 = 0;

        let downloaded_update = update
            .download(
                |chunk_length, content_length| {
                    let content_length = content_length.unwrap_or(0);
                    let window = app
                        .get_webview_window("app")
                        .expect("Cannot get app window");
                    downloaded += chunk_length as u64;

                    let progress = (downloaded * 100) / content_length;

                    let state = ProgressBarState {
                        status: Some(tauri::window::ProgressBarStatus::Normal),
                        progress: Some(progress.into()),
                    };
                    window
                        .set_progress_bar(state)
                        .expect("Failure on update download: downloading");
                },
                || {
                    let window = app
                        .get_webview_window("app")
                        .expect("Cannot get app window");
                    let state = ProgressBarState {
                        status: Some(tauri::window::ProgressBarStatus::None),
                        progress: Some(0),
                    };
                    window
                        .set_progress_bar(state)
                        .expect("Failure on update download: downloaded");
                },
            )
            .await?;

        update
            .install(downloaded_update)
            .expect("Failure installing");
        app.restart();
    }

    Ok(())
}
