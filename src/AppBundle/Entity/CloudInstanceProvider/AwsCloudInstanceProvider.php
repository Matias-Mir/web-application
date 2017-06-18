<?php

namespace AppBundle\Entity\CloudInstanceProvider;

use AppBundle\Entity\CloudInstance\AwsCloudInstance;
use AppBundle\Entity\CloudInstance\CloudInstance;
use AppBundle\Entity\CloudInstanceProvider\ProviderElement\Flavor;
use AppBundle\Entity\CloudInstanceProvider\ProviderElement\Image;
use AppBundle\Entity\CloudInstanceProvider\ProviderElement\Region;
use AppBundle\Entity\RemoteDesktop\RemoteDesktop;
use AppBundle\Entity\RemoteDesktop\RemoteDesktopKind;

class AwsCloudInstanceProvider extends CloudInstanceProvider
{
    protected $flavors = [];
    protected $images = [];
    protected $regions = [];

    protected $kindToRegionToImage = [];

    public function __construct()
    {

        // Never remove a flavor, image or region, because there might still be users
        // who have old desktops with this flavor/image/region

        $this->flavors = [
            new Flavor($this, 'g2.2xlarge', '8 vCPUs, 15 GB RAM, 1 GPU'),
            new Flavor($this, 'g2.8xlarge', '32 vCPUs, 60 GB RAM, 4 GPUs'),
            new Flavor($this, 'c4.4xlarge', '16 vCPUs, 30 GB RAM, no GPU')
        ];

        $this->images = [
            new Image($this, 'ami-14c0107b', '[CURRENT] Gaming for eu-central-1'),
            new Image($this, 'ami-a2437cc4', '[CURRENT] Gaming for eu-west-1'),
            new Image($this, 'ami-96179a80', '[CURRENT] Gaming for us-east-1'),
            new Image($this, 'ami-d8c59fb8', '[CURRENT] Gaming for us-west-1'),
            new Image($this, 'ami-70c0101f', '[CURRENT] CAD for eu-central-1'),
            new Image($this, 'ami-5c39063a', '[CURRENT] CAD for eu-west-1'),
            new Image($this, 'ami-5c169b4a', '[CURRENT] CAD for us-east-1'),
            new Image($this, 'ami-cec79dae', '[CURRENT] CAD for us-west-1'),
            new Image($this, 'ami-71c0101e', '[CURRENT] 3D Media for eu-central-1'),
            new Image($this, 'ami-ff2a1599', '[CURRENT] 3D Media for eu-west-1'),
            new Image($this, 'ami-7d119c6b', '[CURRENT] 3D Media for us-east-1'),
            new Image($this, 'ami-cfc79daf', '[CURRENT] 3D Media for us-west-1'),
            new Image($this, 'ami-51c2123e', '[CURRENT] Unity for eu-central-1'),
            new Image($this, 'ami-ef3b0489', '[CURRENT] Unity for eu-west-1'),
            new Image($this, 'ami-71119c67', '[CURRENT] Unity for us-east-1'),
            new Image($this, 'ami-92c79df2', '[CURRENT] Unity for us-west-1'),
            new Image($this, 'ami-f2fde69e', '[LEGACY] Gaming for eu-central-1'),
            new Image($this, 'ami-10334270', '[LEGACY] Gaming for us-east-1'),
            new Image($this, 'ami-b0c7f2da', '[LEGACY] Gaming for us-west-1')
        ];


        $this->regions = [
            new Region($this, 'eu-central-1', 'cloudprovider.aws.region.eu-central-1'),
            new Region($this, 'eu-west-1', 'cloudprovider.aws.region.eu-west-1'),
            new Region($this, 'us-east-1', 'cloudprovider.aws.region.us-east-1'),
            new Region($this, 'us-west-1', 'cloudprovider.aws.region.us-west-1')
        ];

        $this->kindToRegionToImage = [
            RemoteDesktopKind::GAMING_PRO => [
                'eu-central-1' => $this->getImageByInternalName('ami-14c0107b'),
                'eu-west-1' => $this->getImageByInternalName('ami-a2437cc4'),
                'us-east-1' => $this->getImageByInternalName('ami-96179a80'),
                'us-west-1' => $this->getImageByInternalName('ami-d8c59fb8'),
            ],
            RemoteDesktopKind::CAD_PRO => [
                'eu-central-1' => $this->getImageByInternalName('ami-70c0101f'),
                'eu-west-1' => $this->getImageByInternalName('ami-5c39063a'),
                'us-east-1' => $this->getImageByInternalName('ami-5c169b4a'),
                'us-west-1' => $this->getImageByInternalName('ami-cec79dae'),
            ],
            RemoteDesktopKind::CAD_ULTRA => [
                'eu-central-1' => $this->getImageByInternalName('ami-70c0101f'),
                'eu-west-1' => $this->getImageByInternalName('ami-5c39063a'),
                'us-east-1' => $this->getImageByInternalName('ami-5c169b4a'),
                'us-west-1' => $this->getImageByInternalName('ami-cec79dae'),
            ],
            RemoteDesktopKind::THREED_MEDIA_PRO => [
                'eu-central-1' => $this->getImageByInternalName('ami-71c0101e'),
                'eu-west-1' => $this->getImageByInternalName('ami-ff2a1599'),
                'us-east-1' => $this->getImageByInternalName('ami-7d119c6b'),
                'us-west-1' => $this->getImageByInternalName('ami-cfc79daf'),
            ],
            RemoteDesktopKind::THREED_MEDIA_ULTRA => [
                'eu-central-1' => $this->getImageByInternalName('ami-71c0101e'),
                'eu-west-1' => $this->getImageByInternalName('ami-ff2a1599'),
                'us-east-1' => $this->getImageByInternalName('ami-7d119c6b'),
                'us-west-1' => $this->getImageByInternalName('ami-cfc79daf'),
            ],
            RemoteDesktopKind::UNITY_PRO => [
                'eu-central-1' => $this->getImageByInternalName('ami-51c2123e'),
                'eu-west-1' => $this->getImageByInternalName('ami-ef3b0489'),
                'us-east-1' => $this->getImageByInternalName('ami-71119c67'),
                'us-west-1' => $this->getImageByInternalName('ami-92c79df2'),
            ]
        ];
    }

    /**
     * @return Flavor[]
     */
    public function getFlavors(): array
    {
        return $this->flavors;
    }

    /**
     * @return Image[]
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @return Region[]
     */
    public function getRegions(): array
    {
        return $this->regions;
    }

    public function createInstanceForRemoteDesktopAndRegion(RemoteDesktop $remoteDesktop, Region $region) : CloudInstance
    {
        $instance = new AwsCloudInstance();

        // We use this indirection because it ensures we work with a valid flavor
        $instance->setFlavor($this->getFlavorByInternalName($remoteDesktop->getKind()->getFlavor()->getInternalName()));

        if (array_key_exists($remoteDesktop->getKind()->getIdentifier(), $this->kindToRegionToImage)) {
            if (array_key_exists($region->getInternalName(), $this->kindToRegionToImage[$remoteDesktop->getKind()->getIdentifier()])) {
                $instance->setImage(
                    $this->kindToRegionToImage[$remoteDesktop->getKind()->getIdentifier()][$region->getInternalName()]
                );
            } else {
                throw new \Exception('Cannot match region ' . $region->getInternalName() . ' to an AMI.');
            }
        } else {
            throw new \Exception('Cannot match kind ' . get_class($remoteDesktop->getKind()) . ' to an AMI.');
        }

        if ($instance->getFlavor()->getInternalName() === 'g2.2xlarge') {
            $instance->setRootVolumeSize(60);
            $instance->setAdditionalVolumeSize(200);
        } elseif ($instance->getFlavor()->getInternalName() === 'g2.8xlarge') {
            $instance->setRootVolumeSize(240);
        } elseif ($instance->getFlavor()->getInternalName() === 'c4.4xlarge') {
            $instance->setRootVolumeSize(60);
            $instance->setAdditionalVolumeSize(200);
        } else {
            throw new \Exception('Missing root volume size mapping for flavor ' . $instance->getFlavor()->getInternalName());
        }

        // We use this indirection because it ensures we work with a valid region
        $instance->setRegion($this->getRegionByInternalName($region->getInternalName()));

        return $instance;
    }

    /**
     * @throws \Exception
     */
    public function getHourlyUsageCostsForFlavorImageRegionCombination(Flavor $flavor, Image $image, Region $region) : float
    {
        return $this->getMaximumHourlyUsageCostsForFlavor($flavor);
    }

    public function getMaximumHourlyUsageCostsForFlavor(Flavor $flavor) : float
    {
        if ($flavor->getInternalName() === 'g2.2xlarge') {
            return 1.49;
        }

        if ($flavor->getInternalName() === 'c4.4xlarge') {
            return 1.49;
        }

        if ($flavor->getInternalName() === 'g2.8xlarge') {
            return 4.29;
        }

        throw new \Exception('Unknown flavor ' . $flavor->getInternalName());
    }

    public function getHourlyProvisioningCostsForFlavorImageRegionVolumeSizesCombination(
        Flavor $flavor, Image $image, Region $region, int $rootVolumeSize, int $additionalVolumeSize) : float
    {
        $pricePerGBPerMonth = 0.119; // gp2 Volume type
        $daysPerMonth = 30;
        $hoursPerDay = 24;
        $hoursPerMonth = $daysPerMonth * $hoursPerDay;
        return round(( ($rootVolumeSize + $additionalVolumeSize) * $pricePerGBPerMonth ) / $hoursPerMonth, 2);
    }

}
