# Securing an AvianVisitors install

The collage is intentionally public; its controls are not. Set an admin
password before exposing the Pi outside your LAN.

## Set the admin password

On the Pi, edit the generated configuration as your normal Pi user (the
`/etc/birdnet/birdnet.conf` path is a protected symlink and `sudoedit` will
intentionally refuse it):

```bash
cd ~/BirdNET-Pi
nano birdnet.conf
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

Visiting the menu button in the collage shows an in-page password field before
revealing Settings, System, Logs, or Tools. Successful login creates an
`HttpOnly`, `SameSite=Strict` browser session; no HTTP Basic-auth credential
dialog is used. A blank `CADDY_PWD` leaves the collage public but prevents any
admin login.

`birdnet.conf` is sourced by shell scripts. Keep the value quoted, do not put
it in source control, and restrict local access to the Pi's administrator.

## What the hardened Caddy configuration does

- Password-protects the AvianVisitors admin APIs, direct media directories,
  and the live stream with a server-side session.
- Removes directory browsing.
- Disables the legacy BirdNET-Pi PHP interface, GoTTY terminal, Adminer,
  phpSysInfo, and Tiny File Manager from the web server.
- Grants the PHP/Caddy account only the narrow `systemctl restart` and
  `journalctl` commands the new admin UI needs, instead of passwordless root
  access for arbitrary commands.

For public forwarding, also use Cloudflare Access and keep the Pi on a trusted
network. The in-page password is still sent over the connection, so direct
`http://birdnet.local/` access is appropriate only on a trusted LAN. Cloudflare
provides HTTPS to remote visitors but does not protect direct local access.
