<?php

namespace TeyvatPS\managers;

use Google\Protobuf\Internal\CodedInputStream;
use TeyvatPS\game\entity\Avatar;
use TeyvatPS\game\entity\Entity;
use TeyvatPS\math\Vector3;
use TeyvatPS\network\NetworkServer;
use TeyvatPS\network\protocol\DataPacket;
use TeyvatPS\network\Session;
use TeyvatPS\utils\Logger;

class UnionCmdManager
{

    public static function init(): void
    {
        NetworkServer::registerProcessor(\UnionCmdNotify::class,
            function (Session $session, \UnionCmdNotify $request): void {
                foreach ($request->getCmdList() as $cmd) {
                    $unionPacket = new DataPacket($cmd->getMessageId());
                    $unionPacket->data->parseFromStream(new CodedInputStream($cmd->getBody()));
                    NetworkServer::process($session, $unionPacket);
                }
            });

        NetworkServer::registerProcessor(\CombatInvocationsNotify::class, function (Session $session, \CombatInvocationsNotify $request): void {
            foreach ($request->getInvokeList() as $invoke) {
                switch ($invoke->getArgumentType()) {
                    case \CombatTypeArgument::COMBAT_TYPE_ARGUMENT_EVT_BEING_HIT:
                        $hitInfo = (new \EvtBeingHitInfo());
                        $hitInfo->parseFromStream(new CodedInputStream($invoke->getCombatData()));
                        Logger::log('Received hit info: ' . $hitInfo->serializeToJsonString());
                        return;
                    case \CombatTypeArgument::COMBAT_TYPE_ARGUMENT_ENTITY_MOVE:
                        $moveInfo = (new \EntityMoveInfo());
                        $moveInfo->parseFromStream(new CodedInputStream($invoke->getCombatData()));
                        $entity = $session->getWorld()->getEntityById($moveInfo->getEntityId());
                        if (!$entity instanceof Entity) {
                            return;
                        }
                        if ($moveInfo->getMotionInfo()->getPos() === null) {
                            return;
                        }
                        if($moveInfo->getMotionInfo()->getState() === \MotionState::MOTION_STATE_STANDBY)
                        {
                            $entity->setState(\MotionState::MOTION_STATE_STANDBY);
                            return;
                        }
                        //calculate the speed
                        $entity->setState($moveInfo->getMotionInfo()->getState());
                        $rotation = $moveInfo->getMotionInfo()->getRot();
                        $entity->setRotation(new Vector3($rotation->getX(), $rotation->getY(), $rotation->getZ()));
                        $speed = $moveInfo->getMotionInfo()->getSpeed();
                        $entity->setSpeed(new Vector3($speed->getX(), $speed->getY(), $speed->getZ()));
                        $motion = $moveInfo->getMotionInfo()->getPos();
                        //TODO: calculate and correct the movement
                        $entity->setMotion(new Vector3($motion->getX(), $motion->getY(), $motion->getZ()));
                        if ($entity instanceof Avatar) {
                            $session->getPlayer()->setPosition(new Vector3($motion->getX(), $motion->getY(), $motion->getZ()));
                        }
                        return;
                    default:
                        return;
                }
            }
        });
    }
}
