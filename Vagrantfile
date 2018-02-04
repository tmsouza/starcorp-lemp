# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'json'

VAGRANTFILE_API_VERSION ||= "2"
confDir = $confDir ||= File.expand_path(File.dirname(__FILE__))

serverJsonPath = confDir + "/Server.json"

require File.expand_path(File.dirname(__FILE__) + '/scripts/lemp.rb')

Vagrant.require_version '>= 1.9.0'

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

    if File.exist? serverJsonPath then
        settings = JSON::parse(File.read(serverJsonPath))
    else
        abort "Server settings file not found in #{confDir}"
    end

    Lemp.configure(config, settings)

    if Vagrant.has_plugin?('vagrant-hostsupdater')
        config.hostsupdater.aliases = settings['sites'].map { |site| site['map'] }
    elsif Vagrant.has_plugin?('vagrant-hostmanager')
        config.hostmanager.enabled = true
        config.hostmanager.manage_host = true
        config.hostmanager.aliases = settings['sites'].map { |site| site['map'] }
    end
end
