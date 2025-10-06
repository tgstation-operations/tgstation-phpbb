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
  
  setup-script = groupname: cache-path: avatars-path: pkgs.writeShellScriptBin "tgstation-phpbb-setup" ''
    mkdir -p ${temp-generations-directory}
    old_generations=($(ls -d ${temp-generations-directory}/*))
    generation_path="${temp-generations-directory}/$(uuidgen)"
    
    mkdir -m 640 -p $generation_path
    cp -r ${package}/* $generation_path/

    rm -rf $generation_path/cache
    ln -s ${cache-path} $generation_path/cache

    cp -r $generation_path/images/avatars/upload/* ${avatars-path}/
    rm -rf $generation_path/source/images/avatars/upload
    ln -s ${avatars-path} $generation_path/images/avatars/upload

    unlink ${temp-source-directory} 2>/dev/null || true
    ln -s $generation_path ${temp-source-directory}
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
    tgstation-phpbb = temp-source-directory;

    users.users.tgstation-phpbb = {
      isSystemUser = true;
      createHome = false;
      group = cfg.groupname;
    };

    systemd.services.tgstation-phpbb= {
        description = "tgstation-phpbb setup";
        serviceConfig = {
            Type = "oneshot";
            User = cfg.username;
            ExecStart = setup-script;
        };
        wantedBy = [ "multi-user.target" ];
    };
  };
}
