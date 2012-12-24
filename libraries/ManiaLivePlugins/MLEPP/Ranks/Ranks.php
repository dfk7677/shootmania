<?php

/**
 * MLEPP - ManiaLive Extending Plugin Pack for ShootMania
 *
 * -- MLEPP Plugin --
 * @name Ranks
 * @date 22-12-2012
 * @version 0.5.0
 * @website mlepp.trackmania.nl
 * @package MLEPP
 *
 * @author The MLEPP team
 * @copyright 2010 - 2012
 *
 * ---------------------------------------------------------------------
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * You are allowed to change things or use this in other projects, as
 * long as you leave the information at the top (name, date, version,
 * website, package, author, copyright) and publish the code under
 * the GNU General Public License version 3.
 * ---------------------------------------------------------------------
 */

namespace ManiaLivePlugins\MLEPP\Ranks;

use ManiaLive\Utilities\Console;
use DedicatedApi\Connection;
use ManiaLive\Data\Storage;
use ManiaLive\Features\Admin\AdminGroup;
use ManiaLive\Config\Loader;
use ManiaLive\Utilities\Logger;

use ManiaLivePlugins\MLEPP\Core\Core;

use ManiaLivePlugins\MLEPP\Ranks\Gui\Windows\ListWindow;

class Ranks extends \ManiaLive\PluginHandler\Plugin {

	public $ranks = array('0' => 'Private',
						  '150' => 'Private First Class',
						  '500' => 'Lance Corporal',
						  '800' => 'Corporal',
						  '2500' => 'Sergeant',
						  '5000' => 'Staff Sergeant',
						  '8000' => 'Gunnery Sergeant',
						  '20000' => 'Master Sergeant',
						  '30000' => 'First Sergeant',
						  '40000' => 'Master Gunnery Sergeant',
						  '50000' => 'Sergeant Major',
						  '60000' => '2nd Lieutenant',
						  '75000' => '1st Lieutenant',
						  '90000' => 'Captain',
						  '115000' => 'Major',
						  '125000' => 'Lieutenant Colonel',
						  '150000' => 'Colonel',
						  '180000' => 'Brigadier General',
						  '200000' => 'Major General',
						  '220000' => 'Lieutenant General',
						  '250000' => 'General');
	public $players = array();

	/**
	 * onInit()
	 * Function called on initialisation of ManiaLive.
	 *
	 * @return void
	 */

	function onInit() {
		$this->setVersion('0.5.0');
		$this->setPublicMethod('getVersion');
		$this->setPublicMethod('getRank');
	}

	/**
	 * onLoad()
	 * Function called on loading of ManiaLive.
	 *
	 * @return void
	 */

	function onLoad() {
		$this->enableDatabase();
		$this->enableDedicatedEvents();

		Console::println('['.date('H:i:s').'] [MLEPP] Plugin: Ranks v'.$this->getVersion() );
		$this->callPublicMethod('MLEPP\Core', 'registerPlugin', 'Ranks', $this);
		$cmd = $this->registerChatCommand("top100", "top100Command", 0, true);
		$cmd = $this->registerChatCommand("ranks", "ranksCommand", 0, true);

		if(!$this->db->tableExists('kills')) {
			$q = "CREATE TABLE IF NOT EXISTS `kills` (
  					`kill_id` mediumint(9) NOT NULL AUTO_INCREMENT,
  					`kill_victim` varchar(60) NOT NULL,
  					`kill_shooter` varchar(60) NOT NULL,
  					`kill_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  					`kill_mapUid` varchar(60) NOT NULL,
  					PRIMARY KEY (`kill_id`)
				  ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
			$this->db->execute($q);
		}

		if(!$this->db->tableExists('captures')) {
			$q = "CREATE TABLE IF NOT EXISTS `captures` (
  					`capture_id` mediumint(9) NOT NULL AUTO_INCREMENT,
  					`capture_playerLogin` varchar(60) NOT NULL,
					`capture_mapUid` varchar(60) NOT NULL,
  					`capture_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  					PRIMARY KEY (`capture_id`)
				  ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
			$this->db->execute($q);
		}
		
		// check for right database structure, will check even if no players has connected
		$playerinfo = $this->db->execute("SELECT * FROM information_schema.columns WHERE TABLE_NAME='players' and COLUMN_NAME='player_kills';")->fetchObject();				

		if(!isset($playerinfo->TABLE_NAME)) {
			$q = "ALTER TABLE `players`
				  ADD `player_kills` MEDIUMINT( 9 ) NOT NULL DEFAULT '0',
				  ADD `player_deaths` MEDIUMINT( 9 ) NOT NULL DEFAULT '0',
				  ADD `player_captures` MEDIUMINT( 9 ) NOT NULL DEFAULT '0'";
			$this->db->execute($q);
		}

		$points = array_keys($this->ranks);
		foreach($this->storage->players as $player) {
			$this->players[$player->login] = $this->getRank($player->login);
		}

		foreach($this->storage->spectators as $player) {
			$this->players[$player->login] = $this->getRank($player->login);
		}
	}

	function onPlayerConnect($login, $isSpectator) {
		$player = $this->storage->getPlayerObject($login);
		$this->players[$player->login] = $this->getRank($player->login);
	}

	function mode_onEndMap($scores) {
		$players = $scores;
		$points = array_keys($this->ranks);

		foreach($players as $player) {
			if (strpos($player, ':') !== false) {
				$arrayplayer = explode(':', $player);
				$playerinfo = $this->storage->getPlayerObject($arrayplayer[0]);
				if($arrayplayer[1] == '') $arrayplayer[1] = 0;
				if(isset($this->players[$arrayplayer[0]])) {
					if($this->ranks[$this->closest($points, ($this->players[$arrayplayer[0]]['score'] + $arrayplayer[1]))] != $this->players[$arrayplayer[0]]['rank']) {
						$this->connection->chatSendServerMessage('$fff»» '.$playerinfo->nickName.'$z$s$39f promoted from $fff'.$this->players[$arrayplayer[0]]['rank'].'$39f to $fff'.$this->ranks[$this->closest($points, ($this->players[$arrayplayer[0]]['score'] + $arrayplayer[1]))].'$39f!');
						Console::println('['.date('H:i:s').'] [MLEPP] [Ranks] '.$playerinfo->login.' promoted from '.$this->players[$arrayplayer[0]]['rank'].' to '.$this->ranks[$this->closest($points, ($this->players[$arrayplayer[0]]['score'] + $arrayplayer[1]))].'!');
					}
				}

				$q = "UPDATE `players` SET `player_points` = '".($this->players[$arrayplayer[0]]['score'] + $arrayplayer[1])."' WHERE `player_login` = '".$arrayplayer[0]."'";
				$this->db->execute($q);

				$this->players[$arrayplayer[0]] = array('score' => ($this->players[$arrayplayer[0]]['score'] + $arrayplayer[1]),
					'rank' => $this->ranks[$this->closest($points, ($this->players[$arrayplayer[0]]['score'] + $arrayplayer[1]))]);
			}
		}
	}

	function mode_onPoleCapture($login) {
		$map = $this->connection->getCurrentMapInfo();

		// Insert kill into the database
		$q = "INSERT INTO `captures` (
				`capture_playerLogin`,
				`capture_mapUid`,
				`capture_time`
			  ) VALUES (
			    '".$login."',
			    '".$map->uId."',
			    '".date('Y-m-d H:i:s')."'
			  )";
		$this->db->execute($q);

		// update capture statistics
		$info = $this->db->execute("SELECT * FROM `players` WHERE `player_login` = '".$login."'")->fetchObject();
		$this->db->execute("UPDATE `players` SET `player_captures` = '".($info->player_captures+1)."' WHERE `player_login` = '".$login."'");
	}

	function mode_onPlayerDeath($victim, $shooter = null) {
		if(is_null($shooter)) return;

		$map = $this->connection->getCurrentMapInfo();

		// Insert kill into the database
		$q = "INSERT INTO `kills` (
				`kill_victim`,
				`kill_shooter`,
				`kill_time`,
				`kill_mapUid`
			  ) VALUES (
			    '".$victim."',
			    '".$shooter."',
			    '".date('Y-m-d H:i:s')."',
			    '".$map->uId."'
			  )";
		$this->db->execute($q);

		// update kill/death statistics
		$shooterinfo = $this->db->execute("SELECT * FROM `players` WHERE `player_login` = '".$shooter."'")->fetchObject();
		$this->db->execute("UPDATE `players` SET `player_kills` = '".($shooterinfo->player_kills+1)."' WHERE `player_login` = '".$shooter."'");

		$victiminfo = $this->db->execute("SELECT * FROM `players` WHERE `player_login` = '".$victim."'")->fetchObject();
		$this->db->execute("UPDATE `players` SET `player_deaths` = '".($victiminfo->player_deaths+1)."' WHERE `player_login` = '".$victim."'");

		Console::println('['.date('H:i:s').'] [MLEPP] [Ranks] '.$victim.' was killed by '.$shooter);
	}
	
	function mode_onFragElite($param){
	$players = explode(';', $param);
	$shooter = str_replace('Shooter:', '', $players[0]);
	$victim = str_replace('Victim:', '', $players[2]);
	$weaponnum = str_replace('WeaponNum:', '', $players[1]);

		$map = $this->connection->getCurrentMapInfo();

		// Insert kill into the database
		$q = "INSERT INTO `kills` (
				`kill_victim`,
				`kill_shooter`,
				`kill_time`,
				`kill_mapUid`
			  ) VALUES (
			    '".$victim."',
			    '".$shooter."',
			    '".date('Y-m-d H:i:s')."',
			    '".$map->uId."'
			  )";
		$this->db->execute($q);

		// update kill/death statistics
		$shooterinfo = $this->db->execute("SELECT * FROM `players` WHERE `player_login` = '".$shooter."'")->fetchObject();
		$this->db->execute("UPDATE `players` SET `player_kills` = '".($shooterinfo->player_kills+1)."' WHERE `player_login` = '".$shooter."'");

		$victiminfo = $this->db->execute("SELECT * FROM `players` WHERE `player_login` = '".$victim."'")->fetchObject();
		$this->db->execute("UPDATE `players` SET `player_deaths` = '".($victiminfo->player_deaths+1)."' WHERE `player_login` = '".$victim."'");

		Console::println('['.date('H:i:s').'] [MLEPP] [Ranks] '.$victim.' was killed by '.$shooter);
	}

	function ranksCommand($login, $param1 = null, $param2 = null, $param3 = null) {
		$points = array_keys($this->ranks);
		$window = ListWindow::Create($login);
		$players = array();

		foreach($this->storage->players as $player) {
			try {
				$dbinfo = $this->db->execute("SELECT `player_kills`, `player_deaths`, `player_captures` FROM `players` WHERE `player_login` = '".$player->login."'")->fetchObject();
				$players[] = array('nickname' => $player->nickName,
								   'points' => $this->players[$player->login]['score'],
								   'rank' => $this->players[$player->login]['rank'],
								   'kills' => $dbinfo->player_kills,
								   'deaths' => $dbinfo->player_deaths,
								   'captures' => $dbinfo->player_captures);
			} catch (\Exception $e) { }
		}

		foreach($this->storage->spectators as $player) {
			try {
				$dbinfo = $this->db->execute("SELECT `player_kills`, `player_deaths`, `player_captures` FROM `players` WHERE `player_login` = '".$player->login."'")->fetchObject();
				$players[] = array('nickname' => $player->nickName,
								   'points' => $this->players[$player->login]['score'],
								   'rank' => $this->players[$player->login]['rank'],
								   'kills' => $dbinfo->player_kills,
								   'deaths' => $dbinfo->player_deaths,
								   'captures' => $dbinfo->player_captures);
			} catch(\Exception $e) { }
		}

		$this->array_sort_by_column($players, 'points');
		$players = array_reverse($players);

		$window->setInfos($players, $this->storage->server->name, 'Rankings of players currently on');
		$window->show();
	}

	function top100Command($login, $param1 = null, $param2 = null, $param3 = null) {
		$points = array_keys($this->ranks);
		$window = ListWindow::Create($login);
		$execute = $this->db->execute("SELECT * FROM `players` ORDER BY `player_points` DESC LIMIT 0,100");
		$players = array();
		$i = 0;
		while($player = $execute->fetchObject()) {
			$players[$i] = array('nickname' => $player->player_nickname,
								 'points' => $player->player_points,
								 'rank' => $this->ranks[$this->closest($points, $player->player_points)],
								 'kills' => $player->player_kills,
								 'deaths' => $player->player_deaths,
								 'captures' => $player->player_captures);
			$i++;
		}
		$window->setInfos($players, $this->storage->server->name, 'TOP 100 best players on');
		$window->show();
	}

	function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
		$sort_col = array();
		foreach ($arr as $key=> $row) {
			$sort_col[$key] = $row[$col];
		}

		array_multisort($sort_col, $dir, $arr);
	}

	function getRank($login) {
		$players = array_keys($this->players);
		$points = array_keys($this->ranks);

		if(in_array($login, $players)) {
			return $this->players[$login];
		} else {
			$q = "SELECT `player_captures`, `player_kills`, `player_deaths`, `player_points` FROM `players` WHERE `player_login` = '".$login."'";
			$execute = $this->db->execute($q);
			$info = $execute->fetchObject();

			if($info->player_deaths != 0) {
				$kd = ($info->player_kills/$info->player_deaths);
			} else {
				$kd = $info->player_kills;
			}

			return array('score' => $info->player_points,
						 'rank' => $this->ranks[$this->closest($points, $info->player_points)],
						 'kills' => $info->player_kills,
						 'deaths' => $info->player_deaths,
						 'killdeath' => number_format($kd, 2, ',', ''),
						 'captures' => $info->player_captures);
		}
	}

	function closest($array, $number) {
		sort($array);
		return max(array_intersect($array, range(0,$number)));
	}
}

?>