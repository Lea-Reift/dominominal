use tauri::{window::ProgressBarState, Manager};
use tauri_plugin_dialog::{DialogExt, MessageDialogButtons, MessageDialogKind};
use tauri_plugin_updater::UpdaterExt;

pub async fn update() -> tauri_plugin_updater::Result<()> {
    let app = crate::global::get_app_handle();

    let updater = app
        .updater_builder()
        .on_before_exit(move || {
            let handler = crate::global::get_app_handle();
            handler
                .dialog()
                .message("Se va a instalar la actualización. La app se va a cerrar")
                .kind(MessageDialogKind::Warning)
                .title("Instalando Actualización")
                .blocking_show();
            crate::server::kill_laravel_server();
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