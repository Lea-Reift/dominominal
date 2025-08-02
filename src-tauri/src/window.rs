use tauri::Manager;
use std::sync::{Arc, Mutex, LazyLock};
use tauri_plugin_dialog::{DialogExt, MessageDialogKind};
use tokio::sync::broadcast;

#[tauri::command]
pub async fn set_complete() -> Result<(), ()> {
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

static RETRY_COUNT: LazyLock<Arc<Mutex<u32>>> = LazyLock::new(|| Arc::new(Mutex::new(0)));

#[tauri::command]
pub async fn start_window_load_monitoring() -> Result<(), ()> {
    let (tx, mut rx) = broadcast::channel(1);
    
    // Start monitoring task
    tauri::async_runtime::spawn(async move {
        let mut retry_count = 0u32;
        
        loop {
            tokio::time::sleep(tokio::time::Duration::from_secs(2)).await;
            
            // Check if we should stop monitoring
            if rx.try_recv().is_ok() {
                break;
            }
            
            // Try to get response from server
            let response = reqwest::get("http://127.0.0.1:8000/main").await;
            let Ok(response) = response else {
                continue;
            };
            
            // Handle successful response - stop monitoring
            if response.status().is_success() {
                break;
            }
            
            // Handle 500 error
            if response.status().as_u16() != 500 {
                continue;
            }
            
            retry_count += 1;
            
            if retry_count < 3 {
                handle_window_reload().await;
                continue;
            }
            
            show_error_dialog_and_exit().await;
            break;
        }
    });
    
    // Auto-stop monitoring after 30 seconds (timeout)
    tauri::async_runtime::spawn(async move {
        tokio::time::sleep(tokio::time::Duration::from_secs(30)).await;
        let _ = tx.send(());
    });
    
    Ok(())
}

async fn handle_window_reload() {
    let handler = crate::global::get_app_handle();
    let main_window = handler.get_webview_window("app").unwrap();
    let _ = main_window.eval("window.location.reload()");
    
    // Wait a bit longer after reload
    tokio::time::sleep(tokio::time::Duration::from_secs(3)).await;
}

async fn show_error_dialog_and_exit() {
    let handler = crate::global::get_app_handle();
    let dialog = handler.dialog()
        .message("Error del servidor: Se ha producido un error 500 en el servidor después de 3 intentos. La aplicación se cerrará.")
        .kind(MessageDialogKind::Error)
        .title("Error del Servidor");
    
    dialog.show(|_| {
        std::process::exit(1);
    });
}

#[tauri::command]
pub async fn reset_retry_count() {
    let mut count = RETRY_COUNT.lock().unwrap();
    *count = 0;
}