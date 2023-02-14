<?php

const DISTANCE_BEFORE_BRAKE = 2000;

class Pod
{
	public $x = 0;
	public $y = 0;

	/**
	 * Angle to next checkpoint from -180 to 180
	 *
	 * @var int
	 */
	public $nextCheckpointAngle = 0;

	/**
	 * @param int $x
	 * @param int $y
	 * @param int $nextCheckpointAngle
	 */
	public function __construct(int $x, int $y, int $nextCheckpointAngle)
	{
		$this->x = $x;
		$this->y = $y;
		$this->nextCheckpointAngle = $nextCheckpointAngle;
	}

	/**
	 * Return distance from Checkpoint with Pythagore theorem
	 *
	 * @param Checkpoint $distantCheckpoint
	 * @return float
	 */
	public function getDistance(Checkpoint $distantCheckpoint)
	{
		return round(sqrt(pow($distantCheckpoint->x - $this->x, 2) + pow($distantCheckpoint->y - $this->y, 2)));
	}
}

class Road
{

	/**
	 * Get all checkpoints
	 *
	 * @var Checkpoint[]
	 */
	private $allCheckpoints = [];

	/**
	 * Get last passed checkpoint
	 *
	 * @var Checkpoint
	 */
	public $last = null;

	/**
	 * Get currently targeted checkpoint
	 *
	 * @var Checkpoint
	 */
	public $current = null;

	public $lap = 0;

	/**
	 * Add checkpoint to full list
	 */
	public function addCheckpoint(Checkpoint $checkpoint): void
	{
		$this->current = $checkpoint;

		if (in_array($checkpoint, $this->allCheckpoints)) {
			// next checkpoint is the first of the series, it's a new lap
			if ($checkpoint == $this->allCheckpoints[0]) {
				$this->lap++;
			}
			return;
		}

		if (!empty($this->allCheckpoints)) {
			$this->last = $this->allCheckpoints[array_key_last($this->allCheckpoints)];
		}

		$this->allCheckpoints[] = $checkpoint;
	}

	/**
	 * Get next checkpoint after the targeted one
	 *
	 * @return Checkpoint|false
	 */
	public function getNext()
	{

		// While we don't have all checkpoints or current isn't inside the list
		if ($this->lap < 1) {
			error_log("lap is not memorized yet");
			return false;
		}

		$allCheckpoints = new ArrayIterator($this->allCheckpoints);
		$current = $this->current;

		// set internal pointer to current checkpoint
		while ($allCheckpoints->current() != $current) {
			$allCheckpoints->next();
		}
		error_log('current cp x : ' . $allCheckpoints->current()->x);

		// now set the pointer to the next one
		$allCheckpoints->next();
		$current = $allCheckpoints->current();
		if (is_null($current)) {
			$allCheckpoints->rewind();
			$current = $allCheckpoints->current();
		}

		error_log('2nd current cp x : ' . $current->x);


		return $current;
	}

	/**
	 * Draw an imaginary triangle between the pod, and the next 2 checkpoints
	 * return the angles of this triangle
	 *
	 * A => pod
	 * B => first checkpoint
	 * C => last checkpoint
	 *
	 * sides :
	 *  A => pod to first checkpoint
	 *  B => checkpoint to last checkpoint
	 *  C => pod to last checkpoint
	 *
	 * angles :
	 *  Alpha => A - C
	 *  Beta  => B - C
	 *  Gamma => A - B
	 *
	 * @param Pod $pod
	 * @return int[]
	 */
	function getAngles(Pod $pod)
	{
		$cp = $this->current;
		$cp2 = $this->getNext();

		// compute sides
		$sideA = sqrt(pow(($cp->x - $pod->x), 2) + pow(($cp->y - $pod->y), 2));
		$sideB = sqrt(pow(($cp2->x - $cp->x), 2) + pow(($cp2->y - $cp->y), 2));
		$sideC = sqrt(pow(($cp2->x - $pod->x), 2) + pow(($cp2->y - $pod->y), 2));

		// compute angles
		$angleAlpha = acos(($sideB * $sideB + $sideC * $sideC - $sideA * $sideA) / (2 * $sideB * $sideC)) * (180 / M_PI);
		$angleBeta = acos(($sideA * $sideA + $sideC * $sideC - $sideB * $sideB) / (2 * $sideA * $sideC)) * (180 / M_PI);
		$angleGamma = acos(($sideA * $sideA + $sideB * $sideB - $sideC * $sideC) / (2 * $sideA * $sideB)) * (180 / M_PI);

		return array('alpha' => round($angleAlpha), 'beta' => round($angleBeta), 'gamma' => round($angleGamma));
	}

}

class Checkpoint
{

	public $x = 0;
	public $y = 0;

	/**
	 * @param int $x
	 * @param int $y
	 */
	public function __construct(int $x, int $y)
	{
		$this->x = $x;
		$this->y = $y;
	}

}


$boostHasBeenUsed = false;
$tempLastCheckpoint = null;
/** @var null|Checkpoint $nextCheckpoint */
$nextCheckpoint = null;
$lastCheckpointX = 0;
$lastCheckpointY = 0;

$road = new Road();

$allCheckpoints = [];
$currentCheckpoint = 0;
$currentLap = 0;

// game loop
while (TRUE) {
	$thrust = 100;

	fscanf(STDIN, "%d %d %d %d %d %d", $x, $y, $nextCheckpointX, $nextCheckpointY, $nextCheckpointDist, $nextCheckpointAngle);
	fscanf(STDIN, "%d %d", $opponentX, $opponentY);

	$tempCheckpoint = new Checkpoint($nextCheckpointX, $nextCheckpointY);

	if (is_null($nextCheckpoint) || $nextCheckpoint != $tempCheckpoint) {
		error_log("first occurrence or checkpoint passed");
		$nextCheckpoint = $tempCheckpoint;
		$road->addCheckpoint($tempCheckpoint);
	}

	$tempCheckpoint = null;

	$pod = new Pod($x, $y, $nextCheckpointAngle);

	error_log("current lap : " . $road->lap);

	if ($road->lap < 1) {
		// Warm-up lap

		// good angle => full gas
		$thrust = round(100 - ((abs($nextCheckpointAngle) * 100) / 180));
		if ($nextCheckpointDist <= DISTANCE_BEFORE_BRAKE) {
			// close to the next checkpoint, braking
			$thrust = round((($nextCheckpointDist * 100) / DISTANCE_BEFORE_BRAKE));
		} elseif ($nextCheckpointDist >= 8000 && !$boostHasBeenUsed && $nextCheckpointAngle == 0) {
			$thrust = 'BOOST';
			$boostHasBeenUsed = true;
		}
	} else {
		// full lap is known, let's use it
		$lastCheckpointDist = $pod->getDistance($road->last);
		error_log("distance to last cp : " . $lastCheckpointDist);
		error_log("distance to next cp : " . $nextCheckpointDist);

		$angleGamma = $road->getAngles($pod)['gamma'];
		if ($lastCheckpointDist <= DISTANCE_BEFORE_BRAKE) {
			// just getting out of a checkpoint, bad angle => braking
			error_log('on sort du cp, angle : ' . $nextCheckpointAngle);
			$thrust = round(100 - ((abs($nextCheckpointAngle) * 100) / 180));
		}
		if ($nextCheckpointDist <= DISTANCE_BEFORE_BRAKE) {
			// the more gamma, the more thrust
			$thrust = round((abs($angleGamma) * 100) / 180);
		}
	}

	$tempLastCheckpoint = new Checkpoint($nextCheckpointX, $nextCheckpointY);
	echo($nextCheckpointX . " " . $nextCheckpointY . " " . $thrust . "\n");
}
?>
