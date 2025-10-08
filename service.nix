inputs@{
  config,
  pkgs,
  lib,
  ...
}:

let
  package = ./.;
  cfg = config.services.tgstation-phpbb;

  temp-directory = "/tmp/tgstation-phpbb";
  temp-generations-directory = "${temp-directory}/generations";
  temp-source-directory = "${temp-directory}/source";

  username = "tgstation-phpbb";
  
  setup-script = pkgs.writeShellScriptBin "tgstation-phpbb-setup" ''
    mkdir -m 750 -p ${temp-generations-directory}
    old_generations=($(ls -d ${temp-generations-directory}/*))
    generation_path="${temp-generations-directory}/$(${pkgs.libuuid}/bin/uuidgen)"

    echo "Generation Path is $generation_path"
    
    mkdir $generation_path
    cp -r ${package}/* $generation_path/
    chown -R ${username}:${cfg.groupname} $generation_path
    chmod -R 750 $generation_path

    rm -rf $generation_path/cache
    ln -s ${cfg.cache-path} $generation_path/cache

    cp -r $generation_path/images/avatars/upload/* ${cfg.avatars-path}/
    rm -rf $generation_path/source/images/avatars/upload
    ln -s ${cfg.avatars-path} $generation_path/images/avatars/upload

    unlink ${temp-source-directory} 2>/dev/null || true
    ln -s $generation_path ${temp-source-directory}

    rm -rf "''${old_generations[@]}"
  '';
in
{
  options.services.tgstation-phpbb = {
    enable = lib.mkEnableOption "tgstation-phpbb";
    groupname = lib.mkOption {
        type = lib.types.nonEmptyStr;
        default = "tgstation-phpbb";
        description = ''
            The name of group the user used to tgstation-phpbb will belong to.
        '';
    };
    cache-path = lib.mkOption {
        type = lib.types.path;
        default = "/tmp/tgstation-phpbb/cache";
        description = ''
            Path to the phpbb cache directory.
        '';
    };
    avatars-path = lib.mkOption {
        type = lib.types.path;
        default = "/persist/tgstation-phpbb/avatars-upload";
        description = ''
            Path to the phpbb cache directory.
        '';
    };
  };

  config = lib.mkIf cfg.enable {
    users.users."${username}" = {
      isSystemUser = true;
      createHome = false;
      group = cfg.groupname;
    };

    systemd.services.tgstation-phpbb = {
        description = "tgstation-phpbb setup";
        serviceConfig = {
            Type = "oneshot";
            User = username;
            ExecStart = "${setup-script}/bin/tgstation-phpbb-setup";
        };
        wantedBy = [ "multi-user.target" ];
    };
  };
}
