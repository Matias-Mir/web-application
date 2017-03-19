<?php

namespace AppBundle\Entity\CloudInstance;

use AppBundle\Entity\CloudInstanceProvider\CloudInstanceProviderInterface;
use AppBundle\Entity\CloudInstanceProvider\ProviderElement\Flavor;
use AppBundle\Entity\CloudInstanceProvider\ProviderElement\Image;
use AppBundle\Entity\CloudInstanceProvider\ProviderElement\Region;
use AppBundle\Entity\RemoteDesktop\RemoteDesktop;

interface CloudInstanceInterface
{
    public function getCloudInstanceProvider() : CloudInstanceProviderInterface;

    public function setFlavor(Flavor $flavor);

    public function setImage(Image $image);

    public function setRegion(Region $region);

    public function setRemoteDesktop(RemoteDesktop $remoteDesktop);
}

abstract class CloudInstance implements CloudInstanceInterface
{

}
