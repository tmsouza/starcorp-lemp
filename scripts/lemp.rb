class Lemp
    def Lemp.configure(config, settings)
        # Set The VM Provider
        ENV['VAGRANT_DEFAULT_PROVIDER'] = "virtualbox"

        # Configure Local Variable To Access Scripts From Remote Location
        scriptDir = File.dirname(__FILE__)

        # Allow SSH Agent Forward from The Box
        config.ssh.forward_agent = true

        # Configure The Box
        config.vm.define settings["name"] ||= "starcorp-lemp"
        config.vm.box = settings["box"] ||= "starcorp/lemp"
        config.vm.box_version = settings["version"] ||= ">= 0.0.2"
        config.vm.hostname = settings["hostname"] ||= "starcorp"

        # Configure A Private Network IP
        if settings["ip"] != "autonetwork"
            config.vm.network :private_network, ip: settings["ip"] ||= "192.168.10.20"
        else
            config.vm.network :private_network, :ip => "0.0.0.0", :auto_network => true
        end

        # Configure Additional Networks
        if settings.has_key?("networks")
            settings["networks"].each do |network|
                config.vm.network network["type"], ip: network["ip"], bridge: network["bridge"] ||= nil, netmask: network["netmask"] ||= "255.255.255.0"
            end
        end

        # Configure A Few VirtualBox Settings
        config.vm.provider "virtualbox" do |vb|
            vb.name = settings["name"] ||= "starcorp-lemp"
            vb.customize ["modifyvm", :id, "--memory", settings["memory"] ||= "1024"]
            vb.customize ["modifyvm", :id, "--cpus", settings["cpus"] ||= "1"]
            vb.customize ["modifyvm", :id, "--natdnsproxy1", "on"]
            vb.customize ["modifyvm", :id, "--natdnshostresolver1", settings["natdnshostresolver"] ||= "on"]
            vb.customize ["modifyvm", :id, "--ostype", "Ubuntu_64"]
            if settings.has_key?("gui") && settings["gui"]
                vb.gui = true
            end
        end

        # Override Default SSH port on the host
        if (settings.has_key?("default_ssh_port"))
            config.vm.network :forwarded_port, guest: 22, host: settings["default_ssh_port"], auto_correct: false, id: "ssh"
        end

        # Standardize Ports Naming Schema
        if (settings.has_key?("ports"))
            settings["ports"].each do |port|
                port["guest"] ||= port["to"]
                port["host"] ||= port["send"]
                port["protocol"] ||= "tcp"
            end
        else
            settings["ports"] = []
        end

        # Default Port Forwarding
        default_ports = {
            80 => 8000,
            443 => 44300,
            3306 => 33060,
            4040 => 4040,
            5432 => 54320,
            8025 => 8025,
            27017 => 27017
        }

        # Use Default Port Forwarding Unless Overridden
        unless settings.has_key?("default_ports") && settings["default_ports"] == false
            default_ports.each do |guest, host|
                unless settings["ports"].any? { |mapping| mapping["guest"] == guest }
                    config.vm.network "forwarded_port", guest: guest, host: host, auto_correct: true
                end
            end
        end

        # Add Custom Ports From Configuration
        if settings.has_key?("ports")
            settings["ports"].each do |port|
                config.vm.network "forwarded_port", guest: port["guest"], host: port["host"], protocol: port["protocol"], auto_correct: true
            end
        end

        # Configure The Public Key For SSH Access
        if settings.include? 'authorize'
            if settings["authorize"].to_s.length == 0
                puts "Check your Server.json file, you have no authorize specified."
                exit
            end
            settings["authorize"].each do |key|
                if File.exists? File.expand_path(key)
                    config.vm.provision "shell" do |s|
                        s.inline = "echo $1 | grep -xq \"$1\" /home/vagrant/.ssh/authorized_keys || echo \"\n$1\" | tee -a /home/vagrant/.ssh/authorized_keys"
                        s.args = [File.read(File.expand_path(key))]
                    end
                else
                    puts "Check your Server.json file, the path to your authorize does not exist."
                    exit
                end
            end
        end

        # Copy The SSH Private Keys To The Box
        if settings.include? 'keys'
            if settings["keys"].to_s.length == 0
                puts "Check your Server.json file, you have no private key(s) specified."
                exit
            end
            settings["keys"].each do |key|
                if File.exists? File.expand_path(key)
                    config.vm.provision "shell" do |s|
                        s.privileged = false
                        s.inline = "echo \"$1\" > /home/vagrant/.ssh/$2 && chmod 600 /home/vagrant/.ssh/$2"
                        s.args = [File.read(File.expand_path(key)), key.split('/').last]
                    end
                else
                    puts "Check your Server.json file, the path to your private key does not exist."
                    exit
                end
            end
        end

        #Configure The Access Token
        if settings.include? 'tokens'
            if settings["tokens"].to_s.length == 0
                puts "Check your Server.json file, you have no tokens specified."
                exit
            end
            settings["tokens"].each do |key|
                config.vm.provision "shell" do |s|
                    s.privileged = false
                    s.inline = "composer config --global --auth gitlab-token.gitlab.com $1"
                    s.args = [key]
                end
            end
        end

        # Update server
        config.vm.provision "shell" do |s|
            s.path = scriptDir + "/update.sh"
        end

        # Register All Of The Configured Shared Folders
        if settings.include? 'folders'
            settings["folders"].each do |folder|
                if File.exists? File.expand_path(folder["map"])
                    mount_opts = []

                    if (folder["type"] == "nfs")
                        mount_opts = folder["mount_options"] ? folder["mount_options"] : ['actimeo=1', 'nolock']
                    elsif (folder["type"] == "smb")
                        mount_opts = folder["mount_options"] ? folder["mount_options"] : ['vers=3.02', 'mfsymlinks']
                    end

                    # For b/w compatibility keep separate 'mount_opts', but merge with options
                    options = (folder["options"] || {}).merge({ mount_options: mount_opts })

                    # Double-splat (**) operator only works with symbol keys, so convert
                    options.keys.each{|k| options[k.to_sym] = options.delete(k) }

                    config.vm.synced_folder folder["map"], folder["to"], type: folder["type"] ||= nil, **options

                    # Bindfs support to fix shared folder (NFS) permission issue on Mac
                    if (folder["type"] == "nfs")
                        if Vagrant.has_plugin?("vagrant-bindfs")
                            config.bindfs.bind_folder folder["to"], folder["to"]
                        end
                    end
                else
                    config.vm.provision "shell" do |s|
                        s.inline = ">&2 echo \"Unable to mount one of your folders. Please check your folders in Server.json\""
                    end
                end
            end
        end

        # Install All The Configured Nginx Sites
        config.vm.provision "shell" do |s|
            s.path = scriptDir + "/clear-nginx.sh"
        end

        if settings.include? 'sites'
            settings["sites"].each do |site|

                # Create SSL certificate
                config.vm.provision "shell" do |s|
                    s.name = "Creating Certificate: " + site["map"]
                    s.path = scriptDir + "/create-certificate.sh"
                    s.args = [site["map"]]
                end

                type = site["type"] ||= "basic"

                if (type == "symfony")
                    type = "symfony2"
                end

                config.vm.provision "shell" do |s|
                    s.name = "Creating Site: " + site["map"]
                    if site.include? 'params'
                        params = "("
                        site["params"].each do |param|
                            params += " [" + param["key"] + "]=" + param["value"]
                        end
                        params += " )"
                    end
                    s.path = scriptDir + "/serve-#{type}.sh"
                    
                    if (type == "angular")
                        s.args = [site["map"], site["port"] ||= "4200", "80", "443"]
                    else
                        s.args = [site["map"], site["to"], site["port"] ||= "80", site["ssl"] ||= "443", site["php"] ||= "7.2", params ||= "", site["index"] ||= "index", site["store"] ||= "mx"]
                    end
                end
            end
        end

        # Configure All Of The Server Environment Variables
        config.vm.provision "shell" do |s|
            s.name = "Clear Variables"
            s.path = scriptDir + "/clear-variables.sh"
        end

        if settings.has_key?("variables")
            settings["variables"].each do |var|
                config.vm.provision "shell" do |s|
                    s.inline = "echo \"\nenv[$1] = '$2'\" >> /etc/php/5.6/fpm/pool.d/www.conf"
                    s.args = [var["key"], var["value"]]
                end

                config.vm.provision "shell" do |s|
                    s.inline = "echo \"\nenv[$1] = '$2'\" >> /etc/php/7.0/fpm/pool.d/www.conf"
                    s.args = [var["key"], var["value"]]
                end

                config.vm.provision "shell" do |s|
                    s.inline = "echo \"\nenv[$1] = '$2'\" >> /etc/php/7.1/fpm/pool.d/www.conf"
                    s.args = [var["key"], var["value"]]
                end

                config.vm.provision "shell" do |s|
                    s.inline = "echo \"\nenv[$1] = '$2'\" >> /etc/php/7.2/fpm/pool.d/www.conf"
                    s.args = [var["key"], var["value"]]
                end

                config.vm.provision "shell" do |s|
                    s.inline = "echo \"\n# Set Lemp Environment Variable\nexport $1=$2\" >> /home/vagrant/.profile"
                    s.args = [var["key"], var["value"]]
                end
            end

            config.vm.provision "shell" do |s|
                s.inline = "service php5.6-fpm restart; service php7.0-fpm restart; service php7.1-fpm restart; service php7.2-fpm restart;"
            end
        end

        config.vm.provision "shell" do |s|
            s.name = "Restarting Nginx"
            s.inline = "sudo service nginx restart; sudo service php5.6-fpm restart; sudo service php7.0-fpm restart; sudo service php7.1-fpm restart; sudo service php7.2-fpm restart"
        end

        # Configure All Of The Configured Databases
        if settings.has_key?("databases")
            settings["databases"].each do |db|
                config.vm.provision "shell" do |s|
                    s.name = "Creating MySQL Database: " + db
                    s.path = scriptDir + "/create-mysql.sh"
                    s.args = [db]
                end
            end
        end

        # Configure Blackfire.io
        if settings.has_key?("blackfire")
            config.vm.provision "shell" do |s|
                s.path = scriptDir + "/blackfire.sh"
                s.args = [
                    settings["blackfire"][0]["id"],
                    settings["blackfire"][0]["token"],
                    settings["blackfire"][0]["client-id"],
                    settings["blackfire"][0]["client-token"]
                ]
            end
        end

        # Add config file for ngrok
        config.vm.provision "shell" do |s|
            s.path = scriptDir + "/create-ngrok.sh"
            s.args = [settings["ip"]]
            s.privileged = false
        end
    end
end
