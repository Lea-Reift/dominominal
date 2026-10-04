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

    ephpm.args(args.clone())
        .current_dir(realpath.to_owned())
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