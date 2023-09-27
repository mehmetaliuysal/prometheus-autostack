<?php

$selected_language = 'en'; // or 'tr'
$texts = [
    'en' => [
        'title' => 'GelistirMonitorToolInstaller V.0.0.1',
        'choose_installation' => 'Select the installations you want (e.g.: 1,2,3):',
        'prometheus' => 'Prometheus',
        'node_exporter' => 'Node Exporter',
        'mysql_exporter' => 'MySQL Exporter',
        'install_start' => 'Installation of %s %s is starting...',
        'enter_mysql_username' => 'Enter the MySQL username to be created for MySQL Exporter (e.g.: exporter_user):',
        'enter_mysql_password' => 'Enter the MySQL user password to be created for MySQL Exporter:',
        'installation_complete' => 'Installation completed. You can use the `sudo systemctl start service_name` command to start the relevant services.',
        'error_occurred' => 'An error occurred: %s',
    ],
    'tr' => [
        'title' => 'GelistirMonitorToolInstaller V.0.0.1',
        'choose_installation' => 'Hangi kurulumları yapmak istediğinizi seçin (örn: 1,2,3):',
        'prometheus' => 'Prometheus',
        'node_exporter' => 'Node Exporter',
        'mysql_exporter' => 'MySQL Exporter',
        'install_start' => '%s %s kurulumu başlıyor...',
        'enter_mysql_username' => 'MySQL Exporter için oluşturulacak MySQL kullanıcı adını girin (örn: exporter_user):',
        'enter_mysql_password' => 'MySQL Exporter için oluşturulacak MySQL kullanıcı şifresini girin:',
        'installation_complete' => 'Kurulum tamamlandı. İlgili servisleri başlatmak için `sudo systemctl start servis_adı` komutunu kullanabilirsiniz.',
        'error_occurred' => 'Bir hata oluştu: %s',
    ]
];


function execute($command) {
    global $selected_language;
    global $texts;
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        echoColor($texts[$selected_language]['error_occurred'] . implode("\n", $output), "red");
        //exit;
    }
}



function echoColor($text, $color = "default") {
    $colors = [
        "default" => "\033[0m",
        "red" => "\033[31m",
        "green" => "\033[32m",
        "yellow" => "\033[33m",
        "blue" => "\033[34m"
    ];

    echo $colors[$color] . $text . $colors["default"] . PHP_EOL;
}

function getLatestVersion($repo) {
    $ch = curl_init("https://api.github.com/repos/$repo/releases/latest");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $data = curl_exec($ch);
    curl_close($ch);
    if (preg_match('/"tag_name":\s*"([^"]+)"/', $data, $matches)) {
        $version = $matches[1];

        // "v" ile başlayan sürüm bilgisini düzeltme
        if (strpos($version, 'v') === 0) {
            $version = substr($version, 1);
        }

        return $version;
    }

    return false;
}

function getLocalIPAddress() {
    return trim(shell_exec("ip a | grep 'inet ' | grep -v '127.0.0.1' | awk '{print $2}' | cut -d/ -f1"));
}

$local_ip = getLocalIPAddress();

echoColor("\nGelistirMonitorToolInstaller V.0.0.1\n", "green");
echoColor($texts[$selected_language]['choose_installation']."\n", "yellow");
echo "1. Prometheus" . PHP_EOL;
echo "2. Node Exporter" . PHP_EOL;
echo "3. MySQL Exporter" . PHP_EOL;

$input = trim(fgets(STDIN));
$install_choices = explode(',', $input);

if (in_array('1', $install_choices)) {
    // Kullanıcı oluşturma
    execute("sudo useradd --no-create-home --shell /bin/false prometheus");

    // Prometheus create service file
    $prometheusService = <<<EOD
[Unit]
Description=Prometheus
Wants=network-online.target
After=network-online.target

[Service]
User=prometheus
Group=prometheus
Type=simple
ExecStart=/usr/local/bin/prometheus \
--config.file /etc/prometheus/prometheus.yml \
--storage.tsdb.path /var/lib/prometheus/ \
--web.console.templates=/etc/prometheus/consoles \
--web.console.libraries=/etc/prometheus/console_libraries

[Install]
WantedBy=multi-user.target
EOD;

    file_put_contents("/tmp/prometheus.service", $prometheusService);
    execute("sudo mv /tmp/prometheus.service /etc/systemd/system/");

    // Prometheus installation
    $prometheus_version = getLatestVersion('prometheus/prometheus');
    echoColor(sprintf($texts[$selected_language]['install_start'],$texts[$selected_language]['prometheus'],$prometheus_version), "yellow");


    execute("mkdir /etc/prometheus");
    execute("mkdir /var/lib/prometheus");
    execute("chown prometheus:prometheus /etc/prometheus");
    execute("chown prometheus:prometheus /var/lib/prometheus");

    execute("wget https://github.com/prometheus/prometheus/releases/download/v$prometheus_version/prometheus-$prometheus_version.linux-amd64.tar.gz");
    execute("tar xvfz prometheus-$prometheus_version.linux-amd64.tar.gz");
    execute("sudo cp prometheus-$prometheus_version.linux-amd64/prometheus /usr/local/bin/");
    execute("sudo cp prometheus-$prometheus_version.linux-amd64/promtool /usr/local/bin/");
    execute("sudo chown prometheus:prometheus /usr/local/bin/prometheus");
    execute("sudo chown prometheus:prometheus /usr/local/bin/promtool");

    execute("sudo cp -r prometheus-$prometheus_version.linux-amd64/consoles  /etc/prometheus");
    execute("sudo cp -r prometheus-$prometheus_version.linux-amd64/console_libraries  /etc/prometheus");
    execute("sudo chown prometheus:prometheus /etc/prometheus/consoles");
    execute("sudo chown prometheus:prometheus /etc/prometheus/console_libraries");


    $prometheusConfig = <<<EOD
global:
  scrape_interval: 10s

scrape_configs:
- job_name: 'prometheus_master'
  scrape_interval: 5s
  static_configs:
  - targets: ['$local_ip:9090']

- job_name: 'node_exporter'
  scrape_interval: 5s
  static_configs:
  - targets: ['$local_ip:9100']

- job_name: "mysql_exporter"
  tls_config:
    insecure_skip_verify: true
  static_configs:
  - targets: ["$local_ip:9104"]
EOD;

    file_put_contents("/tmp/prometheus.yml", $prometheusConfig);
    execute("sudo mkdir -p /etc/prometheus");
    execute("sudo mv /tmp/prometheus.yml /etc/prometheus/");
    execute("sudo chown -R prometheus:prometheus /etc/prometheus");
    execute("sudo systemctl daemon-reload");
    execute("sudo systemctl restart prometheus");
    execute("sudo systemctl enable prometheus");
}

if (in_array('2', $install_choices)) {
    // create user
    execute("sudo useradd --no-create-home --shell /bin/false node_exporter");

    // Node Exporter create service file
    $nodeExporterService = <<<EOD
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
EOD;

    file_put_contents("/tmp/node_exporter.service", $nodeExporterService);
    execute("sudo mv /tmp/node_exporter.service /etc/systemd/system/");

    // Node Exporter installation
    $node_exporter_version = getLatestVersion('prometheus/node_exporter');
     echoColor(sprintf($texts[$selected_language]['install_start'],$texts[$selected_language]['node_exporter'],$prometheus_version), "yellow");
    execute("wget https://github.com/prometheus/node_exporter/releases/download/v$node_exporter_version/node_exporter-$node_exporter_version.linux-amd64.tar.gz");
    execute("tar xvfz node_exporter-$node_exporter_version.linux-amd64.tar.gz");
    execute("sudo cp node_exporter-$node_exporter_version.linux-amd64/node_exporter /usr/local/bin/");
    execute("sudo chown node_exporter:node_exporter /usr/local/bin/node_exporter");
    execute("sudo systemctl daemon-reload");
    execute("sudo systemctl restart node_exporter");
    execute("sudo systemctl enable node_exporter");
}

if (in_array('3', $install_choices)) {
    // Kullanıcı oluşturma
    execute("sudo useradd --no-create-home --shell /bin/false mysqld_exporter");
    $mysql_exporter_version = getLatestVersion('prometheus/mysqld_exporter');
    // MySQL Exporter Kurulumu
    echoColor(sprintf($texts[$selected_language]['install_start'],$texts[$selected_language]['mysql_exporter'],$prometheus_version), "yellow");

    echoColor($texts[$selected_language]['enter_mysql_username'], "yellow");
    $exporter_user = trim(fgets(STDIN));

    echoColor($texts[$selected_language]['enter_mysql_password'], "yellow");
    $exporter_pass = trim(fgets(STDIN));

    execute("wget https://github.com/prometheus/mysqld_exporter/releases/download/v$mysql_exporter_version/mysqld_exporter-$mysql_exporter_version.linux-amd64.tar.gz");
    execute("tar xvfz mysqld_exporter-$mysql_exporter_version.linux-amd64.tar.gz");
    execute("sudo cp mysqld_exporter-$mysql_exporter_version.linux-amd64/mysqld_exporter /usr/local/bin/");
    execute("sudo chmod +x /usr/local/bin/mysqld_exporter");


    $createUserCmd = <<<EOD
mariadb -e "
CREATE USER '$exporter_user'@'localhost' IDENTIFIED BY '$exporter_pass';
GRANT REPLICATION CLIENT, PROCESS ON *.* TO '$exporter_user'@'localhost';
FLUSH PRIVILEGES;"
EOD;
    execute($createUserCmd);


    execute("sudo mkdir -p /etc/mysql_exporter");
    $myCnfContent = <<<EOD
[client]
user=$exporter_user
password=$exporter_pass
EOD;
    file_put_contents("/etc/mysql_exporter/.my.cnf", $myCnfContent);
    execute("sudo chown -R mysqld_exporter:mysqld_exporter /etc/mysql_exporter");
    execute("sudo chmod 640 /etc/mysql_exporter/.my.cnf");

    $serviceContent = <<<EOD
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
EOD;

    file_put_contents("/tmp/mysqld_exporter.service", $serviceContent);
    execute("sudo mv /tmp/mysqld_exporter.service /etc/systemd/system/");
    execute("sudo systemctl daemon-reload");
    execute("sudo systemctl start mysqld_exporter");
    execute("sudo systemctl enable mysqld_exporter");
}

echoColor($texts[$selected_language]['installation_complete'], "green");
