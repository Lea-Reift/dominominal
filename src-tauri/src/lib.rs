use tauri::Manager;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
  tauri::Builder::default()
    .setup(|app| {
      if cfg!(debug_assertions) {
        app.handle().plugin(
          tauri_plugin_log::Builder::default()
            .level(log::LevelFilter::Info)
            .build(),
        )?;
      }

      // Maximize window
      app.get_webview_window("main").unwrap().maximize().unwrap();
      
      Ok(())
    })
    .run(tauri::generate_context!())
    .expect("error while running tauri application");
}
