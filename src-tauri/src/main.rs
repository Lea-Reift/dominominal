#![windows_subsystem = "windows"]

use std::process::Command;
use std::net::TcpStream;

fn main() {

    let port_is_reachable: bool = TcpStream::connect("127.0.0.1:8000").is_ok();

    if !port_is_reachable {
        Command::new("php")
            .args([
                "artisan",
                "serve",
                "--port=8000"
            ])
            .current_dir("../")
            .spawn()
            .expect("failed to start php server");
    }

    app_lib::run();
}
