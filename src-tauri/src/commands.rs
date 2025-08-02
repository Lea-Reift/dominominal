use std::path::PathBuf;
use tauri::{
    async_runtime::Receiver, path::BaseDirectory, AppHandle, Manager
};
use tauri_plugin_shell::{
    process::{Command, CommandChild, CommandEvent},
    ShellExt,
};

pub fn run_php_command(
    handler: &AppHandle,
    args: Vec<&str>,
    directory: Option<PathBuf>,
    database_path: &PathBuf,
) -> (Receiver<CommandEvent>, CommandChild) {
    let php: Command = handler.shell().sidecar("php").unwrap();
    let realpath = match directory {
        None => handler
            .path()
            .resolve("./resources/app", BaseDirectory::Resource)
            .expect("Fail getting route"),
        _ => directory.unwrap(),
    };

    return php
        .args(args.clone())
        .env("DB_DATABASE", database_path.to_str().unwrap())
        .current_dir(realpath.to_str().expect("Failure getting path"))
        .spawn()
        .expect(&format!("Failure running php command: {:?}", args));
}

pub fn run_artisan_command(
    handler: &AppHandle,
    mut args: Vec<&str>,
    database_path: &PathBuf,
) -> (Receiver<CommandEvent>, CommandChild) {
    let php: Command = handler.shell().sidecar("php").unwrap();

    let realpath: PathBuf = handler
        .path()
        .resolve("./resources/app", BaseDirectory::Resource)
        .expect("Fail getting route");

    args.insert(0, "artisan");

    return php
        .args(args.clone())
        .env("DB_DATABASE", database_path.to_str().unwrap())
        .current_dir(realpath.to_str().expect("Failure getting path"))
        .spawn()
        .expect(&format!("Failure running artisan command: {:?}", args));
}