<?php

/**
 * Copyright (C) 2017-2020   NLOG (엔로그)
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace nlog\SmartUI\FormHandlers;

use nlog\SmartUI\FormHandlers\forms\functions\CalendarFunction;
use nlog\SmartUI\FormHandlers\forms\functions\FlatMoveFunction;
use nlog\SmartUI\FormHandlers\forms\functions\IslandMoveFunction;
use nlog\SmartUI\FormHandlers\forms\functions\ReceiveMoneyFunction;
use nlog\SmartUI\FormHandlers\forms\functions\ShowMoneyInfoFunction;
use nlog\SmartUI\FormHandlers\forms\functions\SpeakerFunction;
use nlog\SmartUI\FormHandlers\forms\functions\TellFunction;
use nlog\SmartUI\FormHandlers\forms\functions\WarpFunction;
use pocketmine\event\Listener;
use nlog\SmartUI\SmartUI;
use nlog\SmartUI\FormHandlers\forms\MainMenu;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\player\Player;
use nlog\SmartUI\FormHandlers\forms\ListMenu;
use nlog\SmartUI\FormHandlers\forms\functions\SpawnFunction;
use nlog\SmartUI\FormHandlers\forms\functions\SendMoneyFunction;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;

class FormManager implements Listener {

    /** @var SmartUI */
    private $owner;

    /** @var SmartUIForm[] */
    protected $functions;

    /** @var ListMenu */
    private $MainMenu;

    /** @var SmartUIForm */
    private $ListMenu;

    /**
     * FormManager constructor.
     *
     * @param SmartUI $owner
     */
    public function __construct(SmartUI $owner) {
        $this->owner = $owner;
        $owner->getServer()->getPluginManager()->registerEvents($this, $owner);

        $this->MainMenu = new MainMenu($owner, $this, 11918);
        $this->ListMenu = new ListMenu($owner, $this, 9182);

        $functions = [];
        //TODO: Implements FormID
        $functions[] = new SpawnFunction($owner, $this, 39388);
        $functions[] = new WarpFunction($owner, $this, 92838);
        $functions[] = new SpeakerFunction($owner, $this, 93821);
        $functions[] = new SendMoneyFunction($owner, $this, 38372);
        $functions[] = new ReceiveMoneyFunction($owner, $this, 48392);
        $functions[] = new CalendarFunction($owner, $this, 91828);
        $functions[] = new IslandMoveFunction($owner, $this, 92810);
        $functions[] = new FlatMoveFunction($owner, $this, 90978);
        $functions[] = new ShowMoneyInfoFunction($owner, $this, 93102);
        $functions[] = new TellFunction($owner, $this, 63881);

        $this->functions = [];
        foreach ($functions as $function) {
            if ($this->owner->getSettings()->canUse($function->getIdentifyName())) {
                if ($function instanceof NeedPluginInterface && !$function->CompatibilityWithPlugin()) {
                    continue;
                }
                $this->functions[$function->getFormId()] = $function;
            }
        }
    }

    /**
     *
     * @param SmartUIForm $form
     * @param bool $override
     * @return bool
     */
    public function addFunction(SmartUIForm $form, bool $override = false): bool {
        if (isset($this->functions[$form->getFormId()]) && !$override) {
            return false;
        }
        $this->functions[$form->getFormId()] = $form;
        return true;
    }

    /**
     *
     * @param int $formId
     * @return bool
     */
    public function removeFunction(int $formId): bool {
        if (isset($this->functions[$formId])) {
            unset($this->functions[$formId]);
            return true;
        }
        return false;
    }

    /**
     *
     * @return SmartUIForm[]
     */
    public function getFunctions(): array {
        return $this->functions;
    }

    /**
     *
     * @param int $formId
     * @return SmartUIForm|NULL
     */
    public function getFunction(int $formId): ?SmartUIForm {
        return $this->functions[$formId] ?? null;
    }

    /**
     *
     * @return MainMenu
     */
    public function getMainMenuForm(): MainMenu {
        return $this->MainMenu;
    }

    /**
     *
     * @return ListMenu
     */
    public function getListMenuForm(): ListMenu {
        return $this->ListMenu;
    }

    public function onInteract(PlayerItemUseEvent $ev) {
        if (!$this->owner->getSettings()->canUseInWorld($ev->getPlayer()->getWorld())) {
            $ev->getPlayer()->sendMessage(SmartUI::$prefix . "사용하실 수 없습니다.");
            return;
        }
        if ($ev->getItem()->getId() . ":" . $ev->getItem()->getMeta() === $this->owner->getSettings()->getItem()) {
            $this->MainMenu->sendPacket($ev->getPlayer());
        }
    }

    public function onTouch(PlayerItemUseEvent $ev) {
        if (!$this->owner->getSettings()->canUseInWorld($ev->getPlayer()->getWorld())) {
            $ev->getPlayer()->sendMessage(SmartUI::$prefix . "사용하실 수 없습니다.");
            return;
        }
        if ($ev->getItem()->getId() . ":" . $ev->getItem()->getMeta() === $this->owner->getSettings()->getItem()) {
            $this->MainMenu->sendPacket($ev->getPlayer());
        }
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $ev) {
        $pk = $ev->getPacket();
        if ($pk instanceof ModalFormResponsePacket) {
            $player = $ev->getOrigin()->getPlayer();
            if ($this->MainMenu->getFormId() === $pk->formId) {
                $this->MainMenu->handleReceive($player, json_decode($pk->formData, true));
            } elseif ($this->ListMenu->getFormId() === $pk->formId) {
                $this->ListMenu->handleReceive($player, json_decode($pk->formData, true));
            } elseif ($this->getFunction($pk->formId) instanceof SmartUIForm) {
                $this->getFunction($pk->formId)->handleReceive($player, json_decode($pk->formData, true));
            }
        }
    }

}
