use std::path::PathBuf;
use tauri::{AppHandle, Manager};

pub fn migrate_app(handler: &AppHandle, database_path: &PathBuf) {
    let (mut receiver, _) = crate::commands::run_artisan_command(handler, ["migrate", "--force"].to_vec(), database_path);

    tauri::async_runtime::block_on(async move {
        println!("Running artisan migrate...");
        receiver.recv().await;
        println!("Artisan migrate done!");
    });
}

pub fn prepare_database(handler: &AppHandle, database_path: &PathBuf) {
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
        migrate_app(handler, database_path);
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

    let migration_files_count: usize = match std::fs::read_dir(&migrations_path) {
        Ok(entries) => entries.flatten().count(),
        Err(_) => {
            println!("Migrations directory not found at: {:?}", migrations_path);
            0 // If directory doesn't exist, assume no migrations
        }
    };

    if migration_files_count > (migrations_count as usize) {
        migrate_app(handler, database_path);
    }
}