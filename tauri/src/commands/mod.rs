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
    println!("realpath: {}", public_path.to_str().expect("polllo"));

    php.args(args.clone())
        .env("EPHPM_SERVER__LISTEN", "0.0.0.0:8000")
        .env("EPHPM_SERVER__DOCUMENT_ROOT", public_path.to_str().expect("Failure getting path"))
        .env("EPHPM_DB__SQLITE__PATH", database_path.to_str().unwrap())
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
