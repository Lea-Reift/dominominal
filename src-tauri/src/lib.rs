use std::{
    path::PathBuf,
    sync::{Mutex, MutexGuard},
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

#[derive(Default)]

struct LaravelInformation {
    server: Option<CommandChild>,
    database_path: Option<PathBuf>,
}

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

                app.handle()
                    .manage(Mutex::new(LaravelInformation::default()));
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

            let database_path: std::path::PathBuf = handler
                .path()
                .resource_dir()
                .expect("Fail getting path")
                .join("resources/app/database/dominominal.sqlite");

            let laravel_state: State<'_, Mutex<LaravelInformation>> = laravel_state(handler);
            let mut laravel_information: MutexGuard<'_, LaravelInformation> =
                laravel_state.lock().expect("Failure getting information");
            laravel_information.database_path = Some(database_path);
            laravel_information.server = Some(start_laravel_server(handler));
            drop(laravel_information);
            prepare_database(handler);
        }

        RunEvent::ExitRequested { .. } | RunEvent::Exit => {
            kill_laravel_server(handler);
        }
        _ => {}
    });
}

#[tauri::command]
async fn set_complete(app: AppHandle) -> Result<(), ()> {
    let splash_window: tauri::WebviewWindow = app.get_webview_window("splashscreen").unwrap();
    let main_window: tauri::WebviewWindow = app.get_webview_window("app").unwrap();

    splash_window.close().unwrap();
    main_window.show().unwrap();
    main_window.set_focus().unwrap();

    let handle: AppHandle = app.clone();

    tauri::async_runtime::spawn(async move {
        update(handle).await.expect("error updating app");
    });
    Ok(())
}

fn kill_laravel_server(handler: &AppHandle) {
    let laravel_state: State<'_, Mutex<LaravelInformation>> = laravel_state(handler);
    let mut laravel_information = laravel_state.lock().expect("Failure getting information");

    if let Some(laravel_server_process) = laravel_information.server.take() {
        laravel_server_process
            .kill()
            .expect("Fail killing laravel server");
    }

    drop(laravel_information);
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
            .buttons(MessageDialogButtons::OkCancelCustom(
                "Si, instalar".to_string(),
                "No, cancelar".to_string(),
            ))
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

fn migrate_app(handler: &AppHandle) {
    let (mut receiver, _) =
        run_php_command(handler, ["artisan", "migrate", "--force"].to_vec(), None);

    tauri::async_runtime::block_on(async move {
        println!("Running artisan migrate...");
        receiver.recv().await;
        println!("Artisan migrate done!");
    });
}

fn prepare_database(handler: &AppHandle) {
    let laravel_state: State<'_, Mutex<LaravelInformation>> = laravel_state(handler);
    let laravel_information: MutexGuard<'_, LaravelInformation> = laravel_state.try_lock().expect("Failure getting information");
    let database_path_wrapper: Option<PathBuf> = laravel_information.database_path.clone();
    let database_path: PathBuf = database_path_wrapper.expect("Missing database path");

    drop(laravel_information);
    if !std::fs::exists(&database_path).unwrap() {
        let _ = std::fs::File::create_new(&database_path);
    }

    let connection = sqlite::open(database_path).expect("Error opening database");

    let mut statement = connection
                .prepare(
                    "SELECT EXISTS(SELECT name FROM sqlite_master WHERE TYPE ='table' AND name = 'migrations') AS has_migrations_table"
                )
                .unwrap();

    statement.next().unwrap();

    let has_migrations_table: bool = statement.read::<i64, _>("has_migrations_table").unwrap() == 1;

    if !has_migrations_table {
        migrate_app(handler);
        return;
    }

    let mut statement: sqlite::Statement<'_> = connection
        .prepare(
            "SELECT COUNT(migration) as migrations_count FROM migrations ORDER BY id DESC LIMIT 1",
        )
        .unwrap();
    statement.next().unwrap();
    let migrations_count: i64 = statement.read::<i64, _>("migrations_count").unwrap();

    let migrations_path: std::path::PathBuf = handler
        .path()
        .resource_dir()
        .expect("Fail getting path")
        .join("resources/app/database/migrations");

    let migration_files_count: usize = std::fs::read_dir(migrations_path)
        .expect("Couldn't access local directory")
        .flatten()
        .count();

    if migration_files_count > (migrations_count as usize) {
        migrate_app(handler);
    }
}

fn laravel_state(handler: &AppHandle) -> State<'_, Mutex<LaravelInformation>> {
    let state: State<'_, Mutex<LaravelInformation>> = handler
        .try_state::<Mutex<LaravelInformation>>()
        .expect("State not found");
    return state;
}
