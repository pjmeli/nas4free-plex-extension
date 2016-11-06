<?php
/*
	plex-gui.php

	WebGUI wrapper for the NAS4Free "Plex Media Server*" add-on created by J.M Rivera.
	(http://forums.nas4free.org/viewtopic.php?f=71&t=11049)
	*Plex(c) (Plex Media Server) is a registered trademark of Plex(c), Inc.

	Copyright (c) 2016 Andreas Schmidhuber
	All rights reserved.

	Portions of NAS4Free (http://www.nas4free.org).
	Copyright (c) 2012-2016 The NAS4Free Project <info@nas4free.org>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice, this
	   list of conditions and the following disclaimer.
	2. Redistributions in binary form must reproduce the above copyright notice,
	   this list of conditions and the following disclaimer in the documentation
	   and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
	ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

	The views and conclusions contained in the software and documentation are those
	of the authors and should not be interpreted as representing official policies,
	either expressed or implied, of the NAS4Free Project.
*/
require("auth.inc");
require("guiconfig.inc");

// Predefined functions overrides.
function gtxt($data) {
	return htmlspecialchars(gettext($data), ENT_QUOTES);
}

$application = "Plex Media Server";
$pgtitle = array(gtxt("Extensions"), "Plex Media Server (Testing)");

// Initialize some variables.
if (is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
	for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) { if (preg_match('/plexinit/', $config['rc']['postinit']['cmd'][$i])) break; ++$i; }
}
//$rootfolder = dirname($config['rc']['postinit']['cmd'][$i]);
$pidfile = "/var/run/plex/plex.pid";
$confdir = "/var/etc/plexconf";
$cwdir = exec("/bin/cat {$confdir}/conf/plex_config | grep 'INSTALL_DIR=' | cut -d'\"' -f2");
$rootfolder = $cwdir;
$configfile = "{$rootfolder}/conf/plex_config";
$versionfile = "{$rootfolder}/version";
$date = strftime('%c');
$logfile = "{$rootfolder}/log/plex_ext.log";

if ($rootfolder == "") $input_errors[] = gtxt("Extension installed with fault");
else {
// Initialize locales.
	$textdomain = "/usr/local/share/locale";
	$textdomain_plex = "/usr/local/share/locale-plex";
	if (!is_link($textdomain_plex)) { mwexec("ln -s {$rootfolder}/locale-plex {$textdomain_plex}", true); }
	bindtextdomain("nas4free", $textdomain_plex);
}
if (is_file("{$rootfolder}/postinit")) unlink("{$rootfolder}/postinit");

// Set default backup directory.
if (1 == mwexec("/bin/cat {$configfile} | grep 'BACKUP_DIR='")) {
	if (is_file("{$configfile}")) exec("/usr/sbin/sysrc -f {$configfile} BACKUP_DIR={$rootfolder}/backup");
}
$backup_path = exec("/bin/cat {$configfile} | grep 'BACKUP_DIR=' | cut -d'\"' -f2");

// Retrieve IP@.
$ipaddr = get_ipaddr($config['interfaces']['lan']['if']);
$url = htmlspecialchars("http://{$ipaddr}:32400/web");
$ipurl = "<a href='{$url}' target='_blank'>{$url}</a>";

if ($_POST) {
	if (isset($_POST['start']) && $_POST['start']) {
		$return_val = mwexec("{$rootfolder}/plexinit -s", true);
		if ($return_val == 0) {
			$savemsg .= gtxt("Plex Media Server started successfully.");
			exec("echo '{$date}: {$application} successfully started' >> {$logfile}");
			}
		else {
			$input_errors[] = gtxt("Plex Media Server startup failed.");
			exec("echo '{$date}: {$application} startup failed' >> {$logfile}");
			}
	}

	if (isset($_POST['stop']) && $_POST['stop']) {
		$return_val = mwexec("{$rootfolder}/plexinit -p && rm -f {$pidfile}", true);
		if ($return_val == 0) {
			$savemsg .= gtxt("Plex Media Server stopped successfully.");
			exec("echo '{$date}: {$application} successfully stopped' >> {$logfile}");
			}
		else {
			$input_errors[] = gtxt("Plex Media Server stop failed.");
			exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
			}
	}

	if (isset($_POST['restart']) && $_POST['restart']) {
		$return_val = mwexec("{$rootfolder}/plexinit -r", true);
		if ($return_val == 0) {
			$savemsg .= gtxt("Plex Media Server restarted successfully.");
			exec("echo '{$date}: {$application} successfully restarted' >> {$logfile}");
			}
		else {
			$input_errors[] = gtxt("Plex Media Server restart failed.");
			exec("echo '{$date}: {$application} restart failed' >> {$logfile}");
			}
	}

	if (isset($_POST['upgrade']) && $_POST['upgrade']) {
		$return_val = mwexec("{$rootfolder}/plexinit -u", true);
		if ($return_val == 0) { $savemsg .= gtxt("Upgrade command successfully executed."); }
		else { $input_errors[] = gtxt("An error has occurred during upgrade process."); }
	}

	if (isset($_POST['backup']) && $_POST['backup']) {
		$return_val = mwexec("mkdir -p {$backup_path} && cd {$rootfolder} && tar -cf plexdata-`date +%Y-%m-%d-%H%M%S`.tar plexdata && mv plexdata-*.tar {$backup_path}", true);
		if ($return_val == 0) {
			$savemsg .= gtxt("Plexdata backup created successfully in {$backup_path}.");
			exec("echo '{$date}: Plexdata backup successfully created' >> {$logfile}");
			}
		else {
			$input_errors[] = gtxt("Plexdata backup failed.");
			exec("echo '{$date}: Plexdata backup failed' >> {$logfile}");
			}
	}

	if (isset($_POST['remove']) && $_POST['remove']) {
		bindtextdomain("nas4free", $textdomain);
		if (is_link($textdomain_plex)) mwexec("rm -f {$textdomain_plex}", true);
		if (is_dir($confdir)) mwexec("rm -rf {$confdir}", true);
		mwexec("rm /usr/local/www/plex-gui.php && rm -R /usr/local/www/ext/plex-gui", true);
		mwexec("{$rootfolder}/plexinit -t", true);
		exec("echo '{$date}: Extension GUI successfully removed' >> {$logfile}");
		header("Location:index.php");
	}

	// Remove only extension related files during cleanup.
	if (isset($_POST['uninstall']) && $_POST['uninstall']) {
		bindtextdomain("nas4free", $textdomain);
		if (is_link($textdomain_plex)) mwexec("rm -f {$textdomain_plex}", true);
		if (is_dir($confdir)) mwexec("rm -rf {$confdir}", true);
		mwexec("rm /usr/local/www/plex-gui.php && rm -R /usr/local/www/ext/plex-gui", true);
		mwexec("{$rootfolder}/plexinit -t", true);
		mwexec("{$rootfolder}/plexinit -p && rm -f {$pidfile}", true);
		mwexec("pkg delete -y plexmediaserver", true);
		if (isset($_POST['plexdata'])) { $uninstall_cmd = "rm -Rf '{$rootfolder}/backup' '{$rootfolder}/conf' '{$rootfolder}/gui' '{$rootfolder}/locale-plex' '{$rootfolder}/plexdata' '{$rootfolder}/system' '{$rootfolder}/plexinit' '{$rootfolder}/README' '{$rootfolder}/release_notes' '{$rootfolder}/version'"; }
		else { $uninstall_cmd = "rm -Rf '{$rootfolder}/backup' '{$rootfolder}/conf' '{$rootfolder}/gui' '{$rootfolder}/locale-plex' '{$rootfolder}/system' '{$rootfolder}/plexinit' '{$rootfolder}/README' '{$rootfolder}/release_notes' '{$rootfolder}/version'"; }
		mwexec($uninstall_cmd, true);
		if (is_link("/usr/local/share/plexmediaserver")) mwexec("rm /usr/local/share/plexmediaserver", true);
		if (is_link("/var/cache/pkg")) mwexec("rm /var/cache/pkg", true);
		if (is_link("/var/db/pkg")) mwexec("rm /var/db/pkg && mkdir /var/db/pkg", true);
		if (is_array($config['rc']['postinit']) && is_array($config['rc']['postinit']['cmd'])) {
			for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) {
				if (preg_match('/plexinit/', $config['rc']['postinit']['cmd'][$i])) { unset($config['rc']['postinit']['cmd'][$i]); }
				++$i;
			}
		}
		write_config();
		// Remove postinit cmd in NAS4Free later versions.
		if (is_array($config['rc']) && is_array($config['rc']['param'])) {
			$postinit_cmd = "{$rootfolder}/plexinit";
			$value = $postinit_cmd;
			$sphere_array = &$config['rc']['param'];
			$updateconfigfile = false;
		if (false !== ($index = array_search_ex($value, $sphere_array, 'value'))) {
			unset($sphere_array[$index]);
			$updateconfigfile = true;
		}
		if ($updateconfigfile) {
			write_config();
			$updateconfigfile = false;
		}
	}
	header("Location:index.php");
}

	if (isset($_POST['save']) && $_POST['save']) {
		// Ensure to have NO whitespace & trailing slash.
		$backup_path = rtrim(trim($_POST['backup_path']),'/');
		if ("{$backup_path}" == "") $backup_path = "{$rootfolder}/backup";
			else exec("/usr/sbin/sysrc -f {$configfile} BACKUP_DIR={$backup_path}");
		if (isset($_POST['enable'])) { 
			exec("/usr/sbin/sysrc -f {$configfile} PLEX_ENABLE=YES");
			mwexec("{$rootfolder}/plexinit", true);
			exec("echo '{$date}: Extension settings saved and enabled' >> {$logfile}");
		}
		else {
			exec("/usr/sbin/sysrc -f {$configfile} PLEX_ENABLE=NO");
			$return_val = mwexec("{$rootfolder}/plexinit -p && rm -f {$pidfile}", true);
			if ($return_val == 0) {
				$savemsg .= gtxt("Plex Media Server stopped successfully.");
				exec("echo '{$date}: Extension settings saved and disabled' >> {$logfile}");
				}
			else {
				$input_errors[] = gtxt("Plex Media Server stop failed.");
				exec("echo '{$date}: {$application} stop failed' >> {$logfile}");
				}
		}
	}
}

// Update some variables.
$plexenable = exec("/bin/cat {$configfile} | grep 'PLEX_ENABLE=' | cut -d'\"' -f2");
$backup_path = exec("/bin/cat {$configfile} | grep 'BACKUP_DIR=' | cut -d'\"' -f2");

function get_version_plex() {
	exec("pkg info -I plexmediaserver", $result);
	return ($result[0]);
}

function get_version_ext() {
	global $versionfile;
	exec("/bin/cat {$versionfile}", $result);
	return ($result[0]);
}

function get_process_info() {
	global $pidfile;
	if (exec("ps acx | grep -f $pidfile")) { $state = '<a style=" background-color: #00ff00; ">&nbsp;&nbsp;<b>'.gtxt("running").'</b>&nbsp;&nbsp;</a>'; }
	else { $state = '<a style=" background-color: #ff0000; ">&nbsp;&nbsp;<b>'.gtxt("stopped").'</b>&nbsp;&nbsp;</a>'; }
	return ($state);
}

function get_process_pid() {
	global $pidfile;
	exec("cat $pidfile", $state); 
	return ($state[0]);
}

if (is_ajax()) {
	$getinfo['info'] = get_process_info();
	$getinfo['pid'] = get_process_pid();
	$getinfo['plex'] = get_version_plex();
	$getinfo['ext'] = get_version_ext();
	render_ajax($getinfo);
}

bindtextdomain("nas4free", $textdomain);
include("fbegin.inc");
bindtextdomain("nas4free", $textdomain_plex);
?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'plex-gui.php', null, function(data) {
		$('#getinfo').html(data.info);
		$('#getinfo_pid').html(data.pid);
		$('#getinfo_plex').html(data.plex);
		$('#getinfo_ext').html(data.ext);
	});
});
//]]>
</script>
<!-- The Spinner Elements -->
<script src="js/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->
<script type="text/javascript">
<!--
function enable_change(enable_change) {
	var endis = !(document.iform.enable.checked || enable_change);
	document.iform.start.disabled = endis;
	document.iform.stop.disabled = endis;
	document.iform.restart.disabled = endis;
	document.iform.upgrade.disabled = endis;
	document.iform.backup.disabled = endis;
	document.iform.backup_path.disabled = endis;
	document.iform.backup_pathbrowsebtn.disabled = endis;
}
//-->
</script>
<form action="plex-gui.php" method="post" name="iform" id="iform" onsubmit="spinner()">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td class="tabcont">
			<?php if (!empty($input_errors)) print_input_errors($input_errors);?>
			<?php if (!empty($savemsg)) print_info_box($savemsg);?>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_titleline_checkbox("enable", gtxt("Plex"), $plexenable == "YES", gtxt("Enable"));?>
				<?php html_text("installation_directory", gtxt("Installation directory"), sprintf(gtxt("The extension is installed in %s"), $rootfolder));?>
				<tr>
					<td class="vncellt"><?=gtxt("Plex version");?></td>
					<td class="vtable"><span name="getinfo_plex" id="getinfo_plex"><?=get_version_plex()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtxt("Extension version");?></td>
					<td class="vtable"><span name="getinfo_ext" id="getinfo_ext"><?=get_version_ext()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtxt("Status");?></td>
					<td class="vtable"><span name="getinfo" id="getinfo"><?=get_process_info()?></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PID:&nbsp;<span name="getinfo_pid" id="getinfo_pid"><?=get_process_pid()?></span></td>
				</tr>
				<?php html_filechooser("backup_path", gtxt("Backup directory"), $backup_path, gtxt("Directory to store archive.tar files of the plexdata folder."), $backup_path, true, 60);?>
				<?php html_text("url", gtxt("WebGUI")." ".gtxt("URL"), $ipurl);?>
			</table>
			<div id="submit">
				<input id="save" name="save" type="submit" class="formbtn" title="<?=gtxt("Save settings");?>" value="<?=gtxt("Save");?>"/>
				<input name="start" type="submit" class="formbtn" title="<?=gtxt("Start Plex Media Server");?>" value="<?=gtxt("Start");?>" />
				<input name="stop" type="submit" class="formbtn" title="<?=gtxt("Stop Plex Media Server");?>" value="<?=gtxt("Stop");?>" />
				<input name="restart" type="submit" class="formbtn" title="<?=gtxt("Restart Plex Media Server");?>" value="<?=gtxt("Restart");?>" />
				<input name="upgrade" type="submit" class="formbtn" title="<?=gtxt("Upgrade Extension and Plex Packages");?>" value="<?=gtxt("Upgrade");?>" />
				<input name="backup" type="submit" class="formbtn" title="<?=gtxt("Backup Plexdata Folder");?>" value="<?=gtxt("Backup");?>" />
			</div>
			<div id="remarks">
				<?php html_remark("note", gtxt("Note"), sprintf(gtxt("Use the %s button to create an archive.tar of the plexdata folder."), gtxt("Backup")));?>
			</div>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_separator();?>
				<?php html_titleline(gtxt("Uninstall"));?>
				<?php html_checkbox("plexdata", gtxt("Plexdata"), false, "<font color='red'>".gtxt("Activate to delete user data (metadata and configuration) as well during the uninstall process.")."</font>", sprintf(gtxt("If not activated the directory %s remains intact on the server."), "{$rootfolder}/plexdata"), false);?>
				<?php html_separator();?>
			</table>
			<div id="submit1">
				<input name="remove" type="submit" class="formbtn" title="<?=gtxt("Remove Plex Extension GUI");?>" value="<?=gtxt("Remove");?>" onclick="return confirm('<?=gtxt("Plex Extension GUI will be removed, ready to proceed?");?>')" />
				<input name="uninstall" type="submit" class="formbtn" title="<?=gtxt("Uninstall Extension and Plex Media Server completely");?>" value="<?=gtxt("Uninstall");?>" onclick="return confirm('<?=gtxt("Plex Extension and Plex packages will be completely removed, ready to proceed?");?>')" />
			</div>
		</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
