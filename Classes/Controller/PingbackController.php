<?php
namespace PHTH\Pongback\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Michael Blunck <michael.blunck@phth.de>, PHTH
 *  
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 *
 * @package pongback
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class PingbackController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * pingbackRepository
	 *
	 * @var \PHTH\Pongback\Domain\Repository\PingbackRepository
	 * @inject
	 */
	protected $pingbackRepository;

	/**
	 * action list
	 *
	 * @return void
	 */
	public function listAction() {
		$pingbacks = $this->pingbackRepository->findAll();
		$this->view->assign('pingbacks', $pingbacks);
	}

	/**
	 * action show
	 *
	 * @param \PHTH\Pongback\Domain\Model\Pingback $pingback
	 * @return void
	 */
	public function showAction(\PHTH\Pongback\Domain\Model\Pingback $pingback) {
		$this->view->assign('pingback', $pingback);
	}

}
?>