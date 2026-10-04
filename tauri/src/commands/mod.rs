use std::{fs, path::PathBuf};
use tauri::{async_runtime::Receiver, path::BaseDirectory::Resource, Manager};
use tauri_plugin_shell::{
    process::{Command, CommandChild, CommandEvent},
    ShellExt,
};
pub fn run_ephpm_command(
    args: Vec<&str>,
    database_path: &PathBuf,
) -> (Receiver<CommandEvent>, CommandChild) {
    let handler = crate::global::get_app_handle();
    let ephpm: Command = handler.shell().sidecar("ephpm").unwrap();

    let realpath: PathBuf = handler
        .path()
        .resolve("resources/app", Resource)
        .expect("Fail getting route");

    // let public_path = handler
    //     .path()
    //     .resolve("resources/app/public", Resource)
    //     .expect("Fail getting public route");

    println!(
        "{}",
        realpath
            .canonicalize()
            .expect("Fallo el realpa")
            .to_str()
            .expect("Fallo el realpa")
    );

    let database_real_path = dunce::canonicalize(database_path).expect("failed");
    let database_real_path_string = database_real_path.to_str().expect("failed");
    // let public_real_path = dunce::canonicalize(public_path).expect("failed");
    // let public_real_path_string = public_real_path.to_str().unwrap_or("failed");
    let justix_real_path = dunce::canonicalize(realpath).expect("failed");
    let justix_real_path_string = justix_real_path.to_str().unwrap_or("failed");

    let toml = format!("\
    [server]
    listen = \"0.0.0.0:8000\"
    document_root ='{justix_real_path_string}'
    index_files = [\"index.php\"]

    # Laravel routes through public/index.php for any URL it doesn't have a static asset for
    fallback = [\"$uri\", \"$uri/\", \"/index.php?$query_string\"]

    [php]
    mode=\"worker\"
    memory_limit = \"256M\"
    max_execution_time = 30
    ini_overrides = [
        [\"display_errors\", \"Off\"],
        [\"error_reporting\", \"E_ALL\"],
    ]

    [php.worker]
    script = \"vendor/bin/ephpm-octane-worker\"

    [db.sqlite]
    path = '{database_real_path_string}'
    ");

    fs::write("./ephpm.toml", toml).expect("TODO: panic message");
    
    ephpm.args(args.clone())
        .current_dir(justix_real_path_string)
        .spawn()
        .expect(&format!("Failure running command: {:?}", args))
}

pub fn run_php_command(
    mut args: Vec<&str>,
    database_path: &PathBuf,
) -> (Receiver<CommandEvent>, CommandChild) {
    args.insert(0, "php");
    run_ephpm_command(args, database_path)
}

pub fn run_artisan_command(
    mut args: Vec<&str>,
    database_path: &PathBuf,
) -> (Receiver<CommandEvent>, CommandChild) {
    args.insert(0, "artisan");
    run_php_command(args, database_path)
}
