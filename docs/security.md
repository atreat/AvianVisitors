# Securing an AvianVisitors install

The collage is intentionally public; its controls are not. Set an admin
password before exposing the Pi outside your LAN.

## Set the admin password

On the Pi, edit the generated configuration:

```bash
sudoedit /etc/birdnet/birdnet.conf
```

Set a long unique password using shell quotes, for example:

```bash
CADDY_PWD='replace-this-with-a-long-unique-password'
```

Then regenerate and validate the Caddy configuration:

```bash
sudo /usr/local/bin/update_caddyfile.sh
```

For an existing AvianVisitors installation, pull these changes first, then
remove the old unrestricted web-server sudo rule:

```bash
cd ~/BirdNET-Pi && git pull
sudo rm -f /etc/sudoers.d/010_caddy-nopasswd
sudo visudo -c
sudo /usr/local/bin/update_caddyfile.sh
```

The narrower `/etc/sudoers.d/020_avian-admin` rule installed by
AvianVisitors remains in place for the admin UI's approved service restarts
and log reads. If it is missing, the UI will safely report a failed restart;
do not recreate the unrestricted `010_caddy-nopasswd` rule.

The protected account is `birdnet`. Visiting the menu button in the collage
will prompt for this password before revealing Settings, System, Logs, or
Tools. A blank `CADDY_PWD` now leaves the collage public but returns `403` for
all management routes.

`birdnet.conf` is sourced by shell scripts. Keep the value quoted, do not put
it in source control, and restrict local access to the Pi's administrator.

## What the hardened Caddy configuration does

- Password-protects the AvianVisitors admin APIs, direct media directories,
  and the live stream.
- Removes directory browsing.
- Disables the legacy BirdNET-Pi PHP interface, GoTTY terminal, Adminer,
  phpSysInfo, and Tiny File Manager from the web server.
- Grants the PHP/Caddy account only the narrow `systemctl restart` and
  `journalctl` commands the new admin UI needs, instead of passwordless root
  access for arbitrary commands.

For public forwarding, also use Cloudflare Access and keep the Pi on a trusted
network. Basic authentication alone is not encrypted on a plain HTTP LAN
connection; Cloudflare provides HTTPS to remote visitors but does not protect
direct `http://birdnet.local/` access.
