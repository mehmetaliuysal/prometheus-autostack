import os
import requests
import subprocess

selected_language = 'en'  # or 'tr'
texts = {
    'en': {
        'title': 'GelistirMonitorToolInstaller V.0.0.1',
        'choose_installation': 'Select the installations you want (e.g.: 1,2,3):',
        'prometheus': 'Prometheus',
        'node_exporter': 'Node Exporter',
        'mysql_exporter': 'MySQL Exporter',
        'install_start': 'Installation of %s %s is starting...',
        'enter_mysql_username': 'Enter the MySQL username to be created for MySQL Exporter (e.g.: exporter_user):',
        'enter_mysql_password': 'Enter the MySQL user password to be created for MySQL Exporter:',
        'installation_complete': 'Installation completed. You can use the `sudo systemctl start service_name` command to start the relevant services.',
        'error_occurred': 'An error occurred: %s',
    },
    'tr': {
        'title': 'GelistirMonitorToolInstaller V.0.0.1',
        'choose_installation': 'Hangi kurulumları yapmak istediğinizi seçin (örn: 1,2,3):',
        'prometheus': 'Prometheus',
        'node_exporter': 'Node Exporter',
        'mysql_exporter': 'MySQL Exporter',
        'install_start': '%s %s kurulumu başlıyor...',
        'enter_mysql_username': 'MySQL Exporter için oluşturulacak MySQL kullanıcı adını girin (örn: exporter_user):',
        'enter_mysql_password': 'MySQL Exporter için oluşturulacak MySQL kullanıcı şifresini girin:',
        'installation_complete': 'Kurulum tamamlandı. İlgili servisleri başlatmak için `sudo systemctl start servis_adı` komutunu kullanabilirsiniz.',
        'error_occurred': 'Bir hata oluştu: %s',
    }
}


def execute(command):
    try:
        result = subprocess.check_output(command, shell=True, stderr=subprocess.STDOUT)
        return result.decode('utf-8')
    except subprocess.CalledProcessError as e:
        print_color(texts[selected_language]['error_occurred'] % e.output.decode('utf-8'), "red")


def print_color(text, color="default"):
    colors = {
        "default": "\033[0m",
        "red": "\033[31m",
        "green": "\033[32m",
        "yellow": "\033[33m",
        "blue": "\033[34m"
    }

    print(colors[color] + text + colors["default"])


def get_latest_version(repo):
    response = requests.get(f"https://api.github.com/repos/{repo}/releases/latest", headers={"User-Agent": "Mozilla/5.0"})
    json_data = response.json()
    version = json_data.get("tag_name", "")

    if version.startswith('v'):
        version = version[1:]

    return version


def get_local_ip_address():
    return subprocess.check_output("hostname -I | cut -d' ' -f1", shell=True).decode('utf-8').strip()

# Main code starts here
local_ip = get_local_ip_address()

print_color("\nGelistirMonitorToolInstaller V.0.0.1\n", "green")
print_color(texts[selected_language]['choose_installation'], "yellow")
print("1. Prometheus")
print("2. Node Exporter")
print("3. MySQL Exporter")

install_choices = input().split(',')

# ... You can continue the process as in the PHP version for each choice.

if '1' in install_choices:
    # Create user
    execute("sudo useradd --no-create-home --shell /bin/false prometheus")

    # Prometheus create service file
    prometheus_service = """
[Unit]
Description=Prometheus
Wants=network-online.target
After=network-online.target

[Service]
User=prometheus
Group=prometheus
Type=simple
ExecStart=/usr/local/bin/prometheus \\
--config.file /etc/prometheus/prometheus.yml \\
--storage.tsdb.path /var/lib/prometheus/ \\
--web.console.templates=/etc/prometheus/consoles \\
--web.console.libraries=/etc/prometheus/console_libraries

[Install]
WantedBy=multi-user.target
"""

    with open("/tmp/prometheus.service", "w") as f:
        f.write(prometheus_service)

    execute("sudo mv /tmp/prometheus.service /etc/systemd/system/")

    # Prometheus installation
    prometheus_version = get_latest_version('prometheus/prometheus')
    print_color(texts[selected_language]['install_start'] % (texts[selected_language]['prometheus'], prometheus_version), "yellow")

    execute("mkdir /etc/prometheus")
    execute("mkdir /var/lib/prometheus")
    execute("chown prometheus:prometheus /etc/prometheus")
    execute("chown prometheus:prometheus /var/lib/prometheus")

    execute(f"wget https://github.com/prometheus/prometheus/releases/download/v{prometheus_version}/prometheus-{prometheus_version}.linux-amd64.tar.gz")
    execute(f"tar xvfz prometheus-{prometheus_version}.linux-amd64.tar.gz")
    execute(f"sudo cp prometheus-{prometheus_version}.linux-amd64/prometheus /usr/local/bin/")
    execute(f"sudo cp prometheus-{prometheus_version}.linux-amd64/promtool /usr/local/bin/")
    execute("sudo chown prometheus:prometheus /usr/local/bin/prometheus")
    execute("sudo chown prometheus:prometheus /usr/local/bin/promtool")

    execute(f"sudo cp -r prometheus-{prometheus_version}.linux-amd64/consoles /etc/prometheus")
    execute(f"sudo cp -r prometheus-{prometheus_version}.linux-amd64/console_libraries /etc/prometheus")
    execute("sudo chown prometheus:prometheus /etc/prometheus/consoles")
    execute("sudo chown prometheus:prometheus /etc/prometheus/console_libraries")

    prometheus_config = f"""
global:
  scrape_interval: 10s

scrape_configs:
- job_name: 'prometheus_master'
  scrape_interval: 5s
  static_configs:
  - targets: ['{local_ip}:9090']

- job_name: 'node_exporter'
  scrape_interval: 5s
  static_configs:
  - targets: ['{local_ip}:9100']

- job_name: "mysql_exporter"
  tls_config:
    insecure_skip_verify: true
  static_configs:
  - targets: ["{local_ip}:9104"]
"""

    with open("/tmp/prometheus.yml", "w") as f:
        f.write(prometheus_config)

    execute("sudo mkdir -p /etc/prometheus")
    execute("sudo mv /tmp/prometheus.yml /etc/prometheus/")
    execute("sudo chown -R prometheus:prometheus /etc/prometheus")
    execute("sudo systemctl daemon-reload")
    execute("sudo systemctl restart prometheus")
    execute("sudo systemctl enable prometheus")


if '2' in install_choices:
    # Create user
    execute("sudo useradd --no-create-home --shell /bin/false node_exporter")

    # Node Exporter create service file
    node_exporter_service = """
[Unit]
Description=Node Exporter
After=network.target

[Service]
ExecStart=/usr/local/bin/node_exporter
Restart=always
User=node_exporter
Group=node_exporter

[Install]
WantedBy=multi-user.target
"""

    with open("/tmp/node_exporter.service", "w") as f:
        f.write(node_exporter_service)

    execute("sudo mv /tmp/node_exporter.service /etc/systemd/system/")

    # Node Exporter installation
    node_exporter_version = get_latest_version('prometheus/node_exporter')
    print_color(texts[selected_language]['install_start'] % (texts[selected_language]['node_exporter'], node_exporter_version), "yellow")

    execute(f"wget https://github.com/prometheus/node_exporter/releases/download/v{node_exporter_version}/node_exporter-{node_exporter_version}.linux-amd64.tar.gz")
    execute(f"tar xvfz node_exporter-{node_exporter_version}.linux-amd64.tar.gz")
    execute(f"sudo cp node_exporter-{node_exporter_version}.linux-amd64/node_exporter /usr/local/bin/")
    execute("sudo chown node_exporter:node_exporter /usr/local/bin/node_exporter")
    execute("sudo systemctl daemon-reload")
    execute("sudo systemctl restart node_exporter")
    execute("sudo systemctl enable node_exporter")


if '3' in install_choices:
    # Create user
    execute("sudo useradd --no-create-home --shell /bin/false mysqld_exporter")
    mysql_exporter_version = get_latest_version('prometheus/mysqld_exporter')

    # MySQL Exporter Installation
    print_color(texts[selected_language]['install_start'] % (texts[selected_language]['mysql_exporter'], mysql_exporter_version), "yellow")

    exporter_user = input(texts[selected_language]['enter_mysql_username'])
    exporter_pass = input(texts[selected_language]['enter_mysql_password'])

    execute(f"wget https://github.com/prometheus/mysqld_exporter/releases/download/v{mysql_exporter_version}/mysqld_exporter-{mysql_exporter_version}.linux-amd64.tar.gz")
    execute(f"tar xvfz mysqld_exporter-{mysql_exporter_version}.linux-amd64.tar.gz")
    execute(f"sudo cp mysqld_exporter-{mysql_exporter_version}.linux-amd64/mysqld_exporter /usr/local/bin/")
    execute("sudo chmod +x /usr/local/bin/mysqld_exporter")

    create_user_cmd = f"""
mariadb -e "
CREATE USER '{exporter_user}'@'localhost' IDENTIFIED BY '{exporter_pass}';
GRANT REPLICATION CLIENT, PROCESS ON *.* TO '{exporter_user}'@'localhost';
FLUSH PRIVILEGES;"
"""
    execute(create_user_cmd)

    execute("sudo mkdir -p /etc/mysql_exporter")
    my_cnf_content = f"""
[client]
user={exporter_user}
password={exporter_pass}
"""

    with open("/etc/mysql_exporter/.my.cnf", "w") as f:
        f.write(my_cnf_content)

    execute("sudo chown -R mysqld_exporter:mysqld_exporter /etc/mysql_exporter")
    execute("sudo chmod 640 /etc/mysql_exporter/.my.cnf")

    service_content = """
[Unit]
Description=Prometheus MySQL Exporter
After=network.target
User=mysqld_exporter
Group=mysqld_exporter

[Service]
Type=simple
ExecStart=/usr/local/bin/mysqld_exporter --config.my-cnf="/etc/mysql_exporter/.my.cnf"

[Install]
WantedBy=default.target
"""

    with open("/tmp/mysqld_exporter.service", "w") as f:
        f.write(service_content)

    execute("sudo mv /tmp/mysqld_exporter.service /etc/systemd/system/")
    execute("sudo systemctl daemon-reload")
    execute("sudo systemctl start mysqld_exporter")
    execute("sudo systemctl enable mysqld_exporter")


# Finally:
print_color(texts[selected_language]['installation_complete'], "green")
