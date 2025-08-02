use tauri::{AppHandle, Manager};

#[tauri::command]
pub async fn set_complete(app: AppHandle) -> Result<(), ()> {
    let splash_window: tauri::WebviewWindow = app.get_webview_window("splashscreen").unwrap();
    let main_window: tauri::WebviewWindow = app.get_webview_window("app").unwrap();

    splash_window.close().unwrap();
    main_window.show().unwrap();
    main_window.set_focus().unwrap();

    let handle: AppHandle = app.clone();

    tauri::async_runtime::spawn(async move {
        crate::updater::update(handle).await.expect("error updating app");
    });
    Ok(())
}