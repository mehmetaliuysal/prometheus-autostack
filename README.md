# prometheus-autostack
Prometheus-AutoStack: A streamlined tool to automate the deployment and configuration of Prometheus, Node Exporter, and MySQL Exporter. Simplify your monitoring setup with a single command.

## Prerequisites

- A Linux-based system
- PHP installed
- `curl` extension enabled in PHP
- Permission to execute system commands (typically requires sudo privileges)
- Internet access to download packages from GitHub

## Installation

1. Clone the repository:
    ```bash
    git clone https://github.com/mehmetaliuysal/prometheus-autostack
    cd prometheus-autostack
    ```

2. Make the script executable:
    ```bash
    chmod +x prometheus_autosetup.php
    ```

3. Run the installer:
    ```bash
    ./prometheus_autosetup.php
    ```

4. Follow the on-screen instructions to select which tools you want to install.

## Features

- Automatic fetching of the latest versions of Prometheus, Node Exporter, and MySQL Exporter.
- Easy-to-follow CLI interface for a seamless setup experience.
- Custom MySQL user creation for the MySQL Exporter.

## Troubleshooting

If you encounter any errors:
- Ensure you have the necessary permissions to execute system commands.
- Check your internet connection to ensure packages can be downloaded.
- Ensure the `curl` extension is enabled in your PHP configuration.

## Contributions

Feel free to fork the repository and submit pull requests for any improvements or fixes you'd like to add.

## License

This tool is open-source and free to use under the MIT License.
