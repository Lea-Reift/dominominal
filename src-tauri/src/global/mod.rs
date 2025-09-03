use std::sync::OnceLock;
use tauri::AppHandle;

static APP_HANDLE: OnceLock<AppHandle> = OnceLock::new();

pub fn init_app_handle(handle: AppHandle) {
    APP_HANDLE.set(handle).expect("Failed to set app handle");
}

pub fn get_app_handle() -> &'static AppHandle {
    APP_HANDLE.get().expect("App handle not initialized")
}