<?php

$swarm = false;

abstract class GameObject {
	public $x;
	public $y;
	public $owner;

	/**
	 * Return distance from checkpoint with Pythagore theorem
	 *
	 * @param GameObject $distant
	 * @return float
	 */
	public function getDistance(GameObject $distant)
	{
		return round(sqrt(pow($distant->x - $this->x, 2) + pow($distant->y - $this->y, 2)));
	}


}

class Point extends GameObject {
	public function __construct($x, $y)
	{
		$this->x = $x;
		$this->y = $y;
	}

	/**
	 * @param GameObject $origin
	 * @return Point
	 */
	static function getFleePosition(Unit $allyQueen, Unit $enemyUnit) {
		$angle = atan2($allyQueen->y - $enemyUnit->y, $allyQueen->x - $enemyUnit->x);

		return new Point(
			round($allyQueen->x + cos($angle) * 200),
			round($allyQueen->y + sin($angle) * 200)
		);
	}

}

class Player {
	public $gold;
	public $touchedSiteId;

	/**
	 * @var $allSites Site[]
	 */
	public $allSites = [];

	/**
	 * @var $allUnits Unit[]
	 */
	public $allUnits = [];

	/**
	 * @var string[]
	 */
	protected static $all_barracks = [
		Player::STRUCTURE_BARRACKS_KNIGHT,
		Player::STRUCTURE_BARRACKS_ARCHER,
		Player::STRUCTURE_BARRACKS_GIANT,
	];

	const STRUCTURE_BARRACKS_KNIGHT = 'BARRACKS-KNIGHT';
	const STRUCTURE_BARRACKS_ARCHER = 'BARRACKS-ARCHER';
	const STRUCTURE_BARRACKS_GIANT = 'BARRACKS-GIANT';



	const STRUCTURE_MINE = 'MINE';
	const STRUCTURE_TOWER = 'TOWER';

	/**
	 * @param Site $site
	 * @return void
	 */
	public function addSite(Site $site) {
		$this->allSites[$site->id] = $site;
	}

	/**
	 * @param Unit $unit
	 * @return void
	 */
	public function addUnit(Unit $unit) {
		$this->allUnits[] = $unit;
	}

	/**
	 * BUILD {siteId}
	 *
	 * @param $siteId
	 * @param $type
	 * @return void
	 */
	public function build($siteId, $type) {
		echo("BUILD $siteId $type\n");
	}

	/**
	 * MOVE x y
	 *
	 * @param GameObject $position
	 * @return void
	 */
	public function move(GameObject $position) {
		echo("MOVE $position->x $position->y\n");
	}

	/**
	 * WAIT
	 *
	 * @return void
	 */
	public function wait() {
		echo("WAIT\n");
	}

	public function train(int $max = 3) {
		$sites = "";
		$i = 0;

		$ownedBarracks = array_reverse($this->getAllOwnedBarracks());

		foreach ( $ownedBarracks as $site ) {
			if($i >= $max) {
				break;
			}
			$sites .= " ".$site->id;
			$i++;
		}
		echo("TRAIN".$sites."\n");
	}

	public function getAllOwnedSites() {
		$sites = [];
		foreach($this->allSites as $site) {
			if ($site->owner !== Site::OWNER_ALLY) {
				continue;
			}
			$sites[] = $site;
		}
		return $sites;
	}

	public function getAllOwnedBarracks() {
		$sites = [];
		foreach($this->allSites as $site) {

			if ($site->owner !== Site::OWNER_ALLY || $site->structureType != Site::STRUCTURE_BARRACK) {
				continue;
			}

			$sites[] = $site;
		}
		return $sites;
	}

	/**
	 * @return Site[]
	 */
	public function getAllOwnedMines() {
		$sites = [];
		foreach($this->allSites as $site) {

			if ($site->owner !== Site::OWNER_ALLY || $site->structureType != Site::STRUCTURE_MINE) {
				continue;
			}

			$sites[] = $site;
		}
		return $sites;
	}

	/**
	 * Return farthest tower ally site from the closest ennemy barrack
	 * @return Site|null
	 */
	public function getFarthestAllyTower(GameObject $unit) {
		$closestEnemyBarrack = $this->getClosestEnemyBarrack($unit);

		// no ennemy site \_oO_/
		if($closestEnemyBarrack === null) {
			return null;
		}

		$maxDistance = 0;
		$farthestTower = null;

		foreach($this->getAllOwnedTower() as $tower) {
			$distance = (int)$tower->getDistance($closestEnemyBarrack);
			if($maxDistance <= $distance){
				$maxDistance = $distance;
				$farthestTower = $tower;
			}
		}

		return $farthestTower;
	}

	/**
	 * Return the farthest point from closest Enemy
	 * @param GameObject $me ally queen
	 * @param GameObject|null $closestEnemy null if not exist
	 *
	 * @return Point
	 */
	public function getFarthestPointFromEnemy(GameObject $me, ?GameObject $closestEnemy) {

		// no close ennemy \_oO_/
		if($closestEnemy === null) {
			return null;
		}

		error_log('me x '.$me->x.' y '.$me->y);
		error_log('closest enemy x '.$closestEnemy->x.' y '.$closestEnemy->y);
		$farthestPoint = Point::getFleePosition($me, $closestEnemy);
		error_log('farthest point x '.$farthestPoint->x.' y '.$farthestPoint->y);

		// hug farthest tower if exist
		$tower = $this->getFarthestAllyTower($me);
		if(!is_null($tower)) {
			$farthestPoint = new Point($tower->x, $tower->y);
		}

		return $farthestPoint;
	}

	public function getClosestEnemySite(GameObject $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->getAllEnemySites() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}

	/**
	 * @return Site[]
	 */
	public function getAllEmptySites() {
		$sites = [];
		foreach($this->allSites as $site) {
			if ($site->owner !== Site::OWNER_EMPTY || $this->isInTowerRange($site)) {
				continue;
			}
			$sites[] = $site;
		}
		return $sites;
	}

	/**
	 * check if tower enemy protect the site
	 *
	 * @param Site $site
	 * @return bool
	 */
	public function isInTowerRange(Site $site) {
		$imInRadius = false;
		foreach($this->getAllEnemyTower() as $tower) {
			// if range > distance, we are in radius
			if($tower->param2 < $site->getDistance($tower)) {
				continue;
			}
			$imInRadius = true;
		}

		return $imInRadius;
	}

	/**
	 * @return Site[]
	 */
	public function getAllEnemySites() {
		$sites = [];
		foreach($this->allSites as $site) {
			if ($site->owner !== Site::OWNER_ENNEMY) {
				continue;
			}
			$sites[] = $site;
		}
		return $sites;
	}

	/**
	 * @return Site[]
	 */
	public function getAllEnemyMines() {
		$sites = [];
		foreach($this->getAllEnemySites() as $site) {
			if ($site->structureType !== Site::STRUCTURE_MINE) {
				continue;
			}
			$sites[] = $site;
		}
		return $sites;
	}


	/**
	 * @return Site[]
	 */
	public function getAllEnemyBarracks() {
		$sites = [];
		foreach($this->getAllEnemySites() as $site) {
			if ($site->structureType !== Site::STRUCTURE_BARRACK) {
				continue;
			}
			$sites[] = $site;
		}
		return $sites;
	}

	/**
	 * @return Site[]
	 */
	public function getAllEnemyTower() {
		$sites = [];
		foreach($this->getAllEnemySites() as $site) {
			if ($site->structureType !== Site::STRUCTURE_TOWER) {
				continue;
			}
			$sites[] = $site;
		}
		return $sites;
	}

	/**
	 * @return Unit[]
	 */
	public function getAllEnemyUnits() {
		$units = [];
		foreach($this->allUnits as $unit) {
			if ($unit->owner !== Site::OWNER_ENNEMY) {
				continue;
			}
			$units[] = $unit;
		}
		return $units;
	}


	/**
	 * @return Site[]
	 */
	public function getAllOwnedTower() {
		$sites = [];
		foreach($this->allSites as $site) {

			if ($site->owner !== Site::OWNER_ALLY || $site->structureType != Site::STRUCTURE_TOWER) {
				continue;
			}

			$sites[] = $site;
		}
		return $sites;
	}

	/**
	 * Get Closest Empty site from unit
	 *
	 * @param Unit $unit
	 * @return Site|null
	 */
	public function getClosestEmptySite(Unit $unit) {
		$minDistance = 999999;
		$closestSite = null;

		$sites = $this->getAllEmptySites() + $this->getAllEnemyMines() + $this->getAllEnemyBarracks();

		foreach($sites as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}

	/**
	 * Get Closest Empty site from unit
	 *
	 * @param Unit $unit
	 * @return Site|null
	 */
	public function getClosestOwnedTower(Unit $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->getAllOwnedTower() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}

	/**
	 * Get Closest Empty site from unit
	 *
	 * @param Unit $unit
	 * @return Site|null
	 */
	public function getClosestSiteIfEnemy(Unit $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->allSites as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}
		if($closestSite->owner != Site::OWNER_ENNEMY) {
			return null;
		}

		return $closestSite;
	}

	/**
	 * Get Closest Empty site with remaining gold
	 *
	 * @param Unit $unit
	 * @return Site|null
	 */
	public function getClosestEmptySiteWithRemainingGold(Unit $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->getAllEmptySites() as $site) {
			if($site->gold == 0) {
				continue;
			}
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}

	/**
	 * Get closest ally Low level Tower site from unit
	 *
	 * @param GameObject $unit
	 * @return Site|null
	 */
	public function getClosestLowTower(GameObject $unit) {
		$minDistance = 999999;
		$closestTower = null;

		foreach($this->getAllOwnedTower() as $tower) {
			// health
			if($tower->param1 >= 401) {
				continue;
			}
			$distance = (int)$tower->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestTower = $tower;
			}
		}

		return $closestTower;
	}

	/**
	 * Get closest ally under-exploited mine site from unit
	 *
	 * @param GameObject $unit
	 * @return Site|null
	 */
	public function getClosestLowMine(GameObject $unit) {
		$minDistance = 999999;
		$closestMine = null;

		foreach($this->getAllOwnedMines() as $mine) {

			// mine size
			if($mine->param1 == $mine->maxMineSize) {
				continue;
			}
			$distance = (int)$mine->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestMine = $mine;
			}

		}

		return $closestMine;
	}

	/**
	 * Get the closest enemy mine from unit
	 *
	 * @param GameObject $me
	 * @return Site|null
	 */
	public function getClosestEnemyMine(GameObject $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->getAllEnemyMines() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}

	/**
	 * Get the closest enemy mine from unit
	 *
	 * @param GameObject $me
	 * @return Site|null
	 */
	public function getClosestEnemyMineOrBarrack(GameObject $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->getAllEnemyMines() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		foreach($this->getAllEnemyBarracks() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}

	/**
	 * Get the closest enemy mine from unit
	 *
	 * @param GameObject $unit
	 * @return Site|null
	 */
	public function getClosestEnemyBarrack(GameObject $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->getAllEnemyBarracks() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}

	/**
	 * Get the closest enemy mine from unit
	 *
	 * @param GameObject $unit
	 * @return Site|null
	 */
	public function getClosestEnemyTower(GameObject $unit) {
		$minDistance = 999999;
		$closestSite = null;

		foreach($this->getAllEnemyTower() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestSite = $site;
			}
		}

		return $closestSite;
	}


	/**
	 * Get the closest enemy unit from unit
	 *
	 * @param GameObject $me
	 * @return Unit|null
	 */
	public function getClosestEnemy(GameObject $me) {
		$minDistance = 1000;
		$closestUnit = null;

		foreach($this->getAllEnemyUnits() as $unit) {

			// mine size
			if($unit->unitType != Unit::KNIGHT) {
				continue;
			}
			$distance = (int)$unit->getDistance($me);
			if($minDistance > $distance){
				$minDistance = $distance;
				$closestUnit = $unit;
			}

		}
		if($minDistance == 600) {
			return null;
		}
		error_log('distance from enemy '.$minDistance);

		return $closestUnit;
	}

	/**
	 * @param Unit $unit
	 * @return GameObject|null
	 */
	public function getFarthestEmptySite(GameObject $unit) {
		$maxDistance = 0;
		$farthestSite = null;

		foreach($this->getAllEmptySites() as $site) {
			$distance = (int)$site->getDistance($unit);
			if($maxDistance < $distance){
				$maxDistance = $distance;
				$farthestSite = $site;
			}
		}

		return $farthestSite;
	}



}

class Site extends GameObject {
	const STRUCTURE_EMPTY = -1;
	const STRUCTURE_BARRACK = 2;
	const STRUCTURE_MINE = 0;
	const STRUCTURE_TOWER = 1;

	const OWNER_EMPTY = -1;
	const OWNER_ALLY = 0;
	const OWNER_ENNEMY = 1;


	public $id;
	public $radius;
	public $gold;
	public $maxMineSize;
	public $structureType;
	public $disponibility;
	public $unitType;
	public $owner;

	/**
	 * Quand il n'y a pas de bâtiment construit : -1
	 * Si c'est une mine, son taux de production (entre 1 et 5), -1 si c'est une mine ennemie.
	 * Si c'est une tour, son nombre de points de vie restants.
	 * Si c'est une caserne, le nombre de tours restant avant que la caserne puisse
	 * à nouveau lancer un cycle d'entraînement d'armées, 0 si elle est disponible.
	 * @var
	 */
	public $param1;

	/**
	 * Quand il n'y a pas de bâtiment construit ou si c'est une mine: -1
	 * Si c'est une tour, son rayon de portée
	 * Si c'est une caserne, le type d'armée qu'elle produit
	 * 0 pour une caserne de chevaliers
	 * 1 pour une caserne d'archers
	 * 2 pour une caserne de géants.
	 */
	public $param2;

	/**
	 * @param $id
	 * @param $x
	 * @param $y
	 * @param $radius
	 */
	public function __construct($id, $x, $y, $radius)
	{
		$this->id = $id;
		$this->x = $x;
		$this->y = $y;
		$this->radius = $radius;
	}

	public function hasUnitInRange(Unit $allyQueen) {
		$imInRadiusOfEnemyTower = false;
		if($this->getDistance($allyQueen) <= $this->param2) {
			$imInRadiusOfEnemyTower = true;
		}
		return $imInRadiusOfEnemyTower;
	}

	public function hasSiteInRange(Unit $allyQueen) {
		$imInRadiusOfEnemyTower = false;
		if($this->getDistance($allyQueen) <= $this->param2) {
			$imInRadiusOfEnemyTower = true;
		}
		return $imInRadiusOfEnemyTower;
	}

}

class Unit extends GameObject {

	const OWNER_EMPTY = -1;
	const OWNER_ALLY = 0;
	const OWNER_ENNEMY = 1;

	const ARCHER = 1;
	const KNIGHT = 0;
	const QUEEN = -1;
	const GIANT = 2;

	/**
	 * ARCHER | KNIGHT | QUEEN | GIANT
	 * @var int
	 */
	public $unitType;

	/**
	 * max = 100
	 * @var int
	 */
	public $health;

	/**
	 * @param $unitType
	 * @param $health
	 */
	public function __construct( $x, $y, $owner, $unitType, $health)
	{
		$this->x = $x;
		$this->y = $y;
		$this->owner = $owner;
		$this->unitType = $unitType;
		$this->health = $health;
	}

}


fscanf(STDIN, "%d", $numSites);
define('TOTAL_SITES', $numSites);


// Create sites
$player = new Player();

for ($i = 0; $i < TOTAL_SITES; $i++) {
	fscanf(STDIN, "%d %d %d %d", $siteId, $x, $y, $radius);
	$site = new Site($siteId, $x, $y, $radius);
	$player->addSite($site);
}


// game loop
while (TRUE) {

	// Update player
	fscanf(STDIN, "%d %d", $gold, $touchedSite);
	$player->gold = $gold;
	// $touchedSite: -1 if none
	$player->touchedSiteId = $touchedSite;

	// Update sites
	foreach($player->allSites as &$site) {
		fscanf(STDIN, "%d %d %d %d %d %d %d", $siteId, $gold, $maxMineSize, $structureType, $owner, $param1, $param2);

		$site->gold = $gold;
		$site->maxMineSize = $maxMineSize;
		$site->structureType = $structureType;
		$site->owner = $owner;
		$site->param1 = $param1;
		$site->param2 = $param2;

	}
	unset($site);

	// update Units
	$player->allUnits = [];
	fscanf(STDIN, "%d", $numUnits);
	for ($i = 0; $i < $numUnits; $i++) {
		// $unitType: -1 = QUEEN, 0 = KNIGHT, 1 = ARCHER
		fscanf(STDIN, "%d %d %d %d %d", $x, $y, $owner, $unitType, $health);
		$unit = new Unit($x, $y, $owner, $unitType, $health);

		$player->addUnit($unit);
		if($unitType == Unit::QUEEN && $owner == Unit::OWNER_ALLY) {
			$allyQueen = $unit;
		}
		if($unitType == Unit::QUEEN && $owner == Unit::OWNER_ENNEMY) {
			$ennemyQueen = $unit;
		}
	}


	// Write an action using echo(). DON'T FORGET THE TRAILING \n
	// To debug: error_log(var_export($var, true)); (equivalent to var_dump)
//	error_log(var_export($units, true));

	// First line: A valid queen action
	// Second line: A set of training instructions
	$closestEmptySite             = $player->getClosestEmptySite($allyQueen);
	$closestTower                 = $player->getClosestOwnedTower($allyQueen);
	$closestSiteWithRemainingGold = $player->getClosestEmptySiteWithRemainingGold($allyQueen);
	$closestUnderExploitedMine    = $player->getClosestLowMine($allyQueen);
	$closestUnderExploitedTower   = $player->getClosestLowTower($allyQueen);
	$closestEnemy                 = $player->getClosestEnemy($allyQueen);

	$closestEnemyTower            = $player->getClosestEnemyTower($allyQueen);

	$countOwnedSites              = count($player->getAllOwnedSites());
	$countOwnedBarracks           = count($player->getAllOwnedBarracks());
	$countOwnedMines              = count($player->getAllOwnedMines());
	$countOwnedTower              = count($player->getAllOwnedTower());
	$countEnemyMines              = count($player->getAllEnemyMines());
	$countEnemyBarracks           = count($player->getAllEnemyBarracks());
	$farthestPointFromEnemy       = $player->getFarthestPointFromEnemy($allyQueen, $closestEnemy);

	$distanceToClosestSite        = $closestEmptySite->getDistance($allyQueen);
	$distanceToClosestTower       = !is_null($closestTower) ? $closestTower->getDistance($allyQueen) : null;

	$queenInRange = $closestEnemyTower && $closestEnemyTower->hasUnitInRange($allyQueen);

	// Build
	$destination = null;
	$structure = Player::STRUCTURE_MINE;
	$site = $closestEmptySite;

	error_log('distance to closest Site '.$closestEmptySite->id.' : '.$distanceToClosestSite);

	if(is_null($closestEnemy) && !$queenInRange && !$countEnemyBarracks) {

		// PEACEFUL TIME, je fais des mines et une barrack
		error_log('--- PEACEFUL TIME ----');
		if($countOwnedBarracks < 1 && $swarm) {
			// deuxieme prio : 1 barrack
			error_log('barrack needed');
			$structure = Player::STRUCTURE_BARRACKS_KNIGHT;
		}
		elseif (!is_null($closestUnderExploitedMine)) {
			// premiere prio : mines full boosted
			error_log('Upgrade mines');
			error_log('closest under exploited mine ' . $closestUnderExploitedMine->id);
			$site = $closestUnderExploitedMine;
		}
		elseif(!is_null($closestSiteWithRemainingGold)) {
			error_log('closest gold '.$closestSiteWithRemainingGold->id);
			$site = $closestSiteWithRemainingGold;
		}
		else {
			error_log('go build tower, nothing less to do /shrug');
			$structure = Player::STRUCTURE_TOWER;
		}


	} else {
		error_log('--- WAR TIME ----');

		// check on gold
		if($player->gold >= 80*2) {
			$swarm = true;
		}

		$distanceToClosestEnemy = $closestEnemy ? $closestEnemy->getDistance($allyQueen) : 1000;
		error_log('distance to closest Enemy '.$distanceToClosestEnemy);

		if($countOwnedBarracks < 1 && $distanceToClosestEnemy >= 400) {
			// deuxieme prio : 1 barrack
			error_log('barrack needed');
			$structure = Player::STRUCTURE_BARRACKS_KNIGHT;
		}
		// WAR TIME, je fuis dans le rayon d'action des tours et j'en construis ou upgrade
		elseif($distanceToClosestTower >= 200) {
			error_log('flee');
			$destination = $farthestPointFromEnemy;
		}
		elseif(!is_null($closestUnderExploitedTower)) {
			error_log('Upgrade tower '.$closestUnderExploitedTower->id);
			$site = $closestUnderExploitedTower;
			$structure = Player::STRUCTURE_TOWER;
		} else {
			error_log('Tower needed');
			$structure = Player::STRUCTURE_TOWER;
		}
	}

	if(!is_null($destination)) {
		$player->move($destination);
	} elseif($structure) {
		$player->build($site->id, $structure);
	} else {
		$player->wait();
	}

	if(!$swarm) {
		$player->train(0);
	} else {
		$player->train();
	}
}

?>
