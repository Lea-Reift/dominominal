use std::fs;
use std::path::PathBuf;
use tauri::{async_runtime::Receiver, path::BaseDirectory, Manager};
use tauri::path::BaseDirectory::Resource;
use tauri_plugin_shell::{
    process::{Command, CommandChild, CommandEvent},
    ShellExt,
};
pub fn run_ephpm_command(
    args: Vec<&str>,
    database_path: &PathBuf,
) -> (Receiver<CommandEvent>, CommandChild) {
    let handler = crate::global::get_app_handle();
    let php: Command = handler.shell().sidecar("ephpm").unwrap();

    let realpath: PathBuf = handler
        .path()
        .resolve("resources/app", Resource)
        .expect("Fail getting route");

    let public_path = handler
        .path()
        .resolve("resources/app/public", Resource)
        .expect("Fail getting public route");

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
    let public_real_path = dunce::canonicalize(public_path).expect("failed");
    let public_real_path_string = public_real_path.to_str().unwrap_or("failed");

    let toml = format!("\
    [server]
    listen = \"0.0.0.0:8000\"
    document_root ='{public_real_path_string}'
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
    
    php.args(args.clone())
        // .env("EPHPM_SERVER__LISTEN", "0.0.0.0:8000")
        // .env("EPHPM_SERVER__DOCUMENT_ROOT", public_real_path.to_str().expect("Failure getting path"))
        // .env("EPHPM_DB__SQLITE__PATH", database_real_path.to_str().unwrap())
        .current_dir(realpath.to_str().expect("Failure getting path"))
        .spawn()
        .expect(&format!("Failure running command: {:?}", args))
}

//
// pub fn run_php_command(
//     mut args: Vec<&str>,
//     directory: Option<PathBuf>,
//     database_path: &PathBuf,
// ) -> (Receiver<CommandEvent>, CommandChild) {
//      let handler = crate::global::get_app_handle();
//     // let php: Command = handler.shell().sidecar("php").unwrap();
//     let php: Command = handler.shell().sidecar("ephpm").unwrap();
//     let realpath = match directory {
//         None => handler
//             .path()
//             .resolve("./resources/app", BaseDirectory::Resource)
//             .expect("Fail getting route"),
//         _ => directory.unwrap(),
//     };
//
//     let mut real_args: Vec<&str> = vec!["php"];
//
//     real_args.append(&mut args);
// }

pub fn run_artisan_command(
    mut args: Vec<&str>,
    database_path: &PathBuf,
) -> (Receiver<CommandEvent>, CommandChild) {
    args.insert(0, "php");
    args.insert(1, "artisan");

    run_ephpm_command(args, database_path)
}
