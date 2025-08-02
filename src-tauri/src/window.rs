use tauri::{AppHandle, Manager};

#[tauri::command]
pub async fn set_complete(_app: AppHandle) -> Result<(), ()> {
    let handler = crate::global::get_app_handle();
    let splash_window: tauri::WebviewWindow = handler.get_webview_window("splashscreen").unwrap();
    let main_window: tauri::WebviewWindow = handler.get_webview_window("app").unwrap();

    splash_window.close().unwrap();
    main_window.show().unwrap();
    main_window.set_focus().unwrap();

    tauri::async_runtime::spawn(async move {
        crate::updater::update().await.expect("error updating app");
    });
    Ok(())
}