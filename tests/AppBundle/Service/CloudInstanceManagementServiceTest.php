<?php

namespace Tests\AppBundle\Service;

use AppBundle\Coordinator\CloudInstance\AwsCloudInstanceCoordinator;
use AppBundle\Coordinator\CloudInstance\CloudInstanceCoordinatorFactory;
use AppBundle\Coordinator\CloudInstance\CloudProviderProblemException;
use AppBundle\Entity\Billing\AccountMovement;
use AppBundle\Entity\Billing\AccountMovementRepository;
use AppBundle\Entity\CloudInstance\AwsCloudInstance;
use AppBundle\Entity\CloudInstance\CloudInstance;
use AppBundle\Entity\CloudInstanceProvider\AwsCloudInstanceProvider;
use AppBundle\Entity\RemoteDesktop\RemoteDesktop;
use AppBundle\Entity\RemoteDesktop\RemoteDesktopKind;
use AppBundle\Entity\User;
use AppBundle\Service\CloudInstanceManagementService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

class CloudInstanceManagementServiceTest extends TestCase
{

    private function getMockCloudInstanceCoordinatorFactory()
    {
        return $this->getMockBuilder(CloudInstanceCoordinatorFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockEntityManager(float $accountBalance, User $user)
    {
        $mockEm = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockEm
            ->expects($this->once())
            ->method('getRepository')
            ->with(AccountMovement::class)
            ->willReturn($this->getMockAccountMovementRepository($accountBalance, $user));

        return $mockEm;
    }

    private function getMockAccountMovementRepository(float $accountBalance, User $user)
    {
        $mockAccountMovementRepository = $this->getMockBuilder(AccountMovementRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAccountMovementRepository
            ->expects($this->any())
            ->method('getAccountBalanceForUser')
            ->with($user)
            ->willReturn($accountBalance);

        return $mockAccountMovementRepository;
    }

    private function getUser(): User
    {
        $user = new User();
        $user->setUsername('userA');

        return $user;
    }

    private function getRemoteDesktop(User $user): RemoteDesktop
    {
        $remoteDesktop = new RemoteDesktop();
        $remoteDesktop->setId('r1');
        $remoteDesktop->setCloudInstanceProvider(new AwsCloudInstanceProvider());
        $remoteDesktop->setKind(RemoteDesktopKind::createRemoteDesktopKind(RemoteDesktopKind::GAMING_PRO));
        $remoteDesktop->setUser($user);

        return $remoteDesktop;
    }

    private function getCloudInstance(RemoteDesktop $remoteDesktop) : CloudInstance
    {
        $awsCloudInstanceProvider = new AwsCloudInstanceProvider();
        $cloudInstance = new AwsCloudInstance();
        $cloudInstance->setId('c1');
        $cloudInstance->setEc2InstanceId('ec1');
        $cloudInstance->setRemoteDesktop($remoteDesktop);
        $cloudInstance->setFlavor($awsCloudInstanceProvider->getFlavorByInternalName('g2.2xlarge'));
        $cloudInstance->setImage($awsCloudInstanceProvider->getImageByInternalName('ami-f2fde69e'));
        $cloudInstance->setRegion($awsCloudInstanceProvider->getRegionByInternalName('eu-central-1'));

        return $cloudInstance;
    }

    private function getInput() : ArrayInput
    {
        return new ArrayInput(
            [
                'awsApiKey' => 'foo',
                'awsApiSecret' => 'bar',
                'awsKeypairPrivateKeyFile' => 'baz',
                'paperspaceApiKey' => 'bak'
            ],
            new InputDefinition([
                new InputArgument('awsApiKey', InputArgument::REQUIRED),
                new InputArgument('awsApiSecret', InputArgument::REQUIRED),
                new InputArgument('awsKeypairPrivateKeyFile', InputArgument::REQUIRED),
                new InputArgument('paperspaceApiKey', InputArgument::REQUIRED)
            ])
        );
    }


    public function testScheduledForLaunchIsNotLaunchedIfBalanceInsufficient()
    {
        $user = $this->getUser();
        $remoteDesktop = $this->getRemoteDesktop($user);
        $cloudInstance = $this->getCloudInstance($remoteDesktop);
        $input = $this->getInput();
        $output = new BufferedOutput();

        $mockEm = $this->getMockEntityManager(0.0, $user);


        $cloudInstanceManagementService = new CloudInstanceManagementService(
            $mockEm,
            $this->getMockCloudInstanceCoordinatorFactory()
        );


        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_SCHEDULED_FOR_LAUNCH);

        $cloudInstanceManagementService->manageCloudInstance($cloudInstance, $input, $output);


        $loglines = $output->fetch();

        $this->assertSame(CloudInstance::RUNSTATUS_SCHEDULED_FOR_LAUNCH, $cloudInstance->getRunstatus());
        $this->assertContains('Action: would launch the cloud instance, but owner has insufficient balance', $loglines);
        $this->assertContains('Interval costs would be 1.95, balance is only 0', $loglines);
    }


    public function testScheduledForLaunchIsLaunchedIfBalanceSufficient()
    {
        $user = $this->getUser();
        $remoteDesktop = $this->getRemoteDesktop($user);
        $cloudInstance = $this->getCloudInstance($remoteDesktop);
        $input = $this->getInput();
        $output = new BufferedOutput();

        $mockEm = $this->getMockEntityManager(10.0, $user);

        $mockAwsCloudInstanceCoordinator = $this->getMockBuilder(AwsCloudInstanceCoordinator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAwsCloudInstanceCoordinator
            ->expects($this->once())
            ->method('triggerLaunchOfCloudInstance')
            ->with($cloudInstance);

        $mockAwsCloudInstanceCoordinator
            ->expects($this->once())
            ->method('updateCloudInstanceWithProviderSpecificInfoAfterLaunchWasTriggered')
            ->with($cloudInstance);

        $mockCloudInstanceCoordinatorFactory = $this->getMockCloudInstanceCoordinatorFactory();
        $mockCloudInstanceCoordinatorFactory
            ->expects($this->once())
            ->method('getCloudInstanceCoordinatorForCloudInstance')
            ->willReturn($mockAwsCloudInstanceCoordinator);

        $cloudInstanceManagementService = new CloudInstanceManagementService(
            $mockEm,
            $mockCloudInstanceCoordinatorFactory
        );


        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_SCHEDULED_FOR_LAUNCH);

        $cloudInstanceManagementService->manageCloudInstance($cloudInstance, $input, $output);


        $loglines = $output->fetch();

        $this->assertSame(CloudInstance::RUNSTATUS_LAUNCHING, $cloudInstance->getRunstatus());
        $this->assertContains('Action: launching the cloud instance', $loglines);
    }


    public function testTerminatingInstanceIsSetToTerminatedIfInstanceNotFoundAtProvider()
    {
        $user = $this->getUser();
        $remoteDesktop = $this->getRemoteDesktop($user);
        $cloudInstance = $this->getCloudInstance($remoteDesktop);
        $input = $this->getInput();
        $output = new BufferedOutput();

        $mockEm = $this->getMockEntityManager(10.0, $user);

        $mockAwsCloudInstanceCoordinator = $this->getMockBuilder(AwsCloudInstanceCoordinator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAwsCloudInstanceCoordinator
            ->expects($this->once())
            ->method('triggerTerminationOfCloudInstance')
            ->with($cloudInstance)
            ->willThrowException(new CloudProviderProblemException('', CloudProviderProblemException::CODE_INSTANCE_UNKNOWN));

        $mockCloudInstanceCoordinatorFactory = $this->getMockCloudInstanceCoordinatorFactory();
        $mockCloudInstanceCoordinatorFactory
            ->expects($this->once())
            ->method('getCloudInstanceCoordinatorForCloudInstance')
            ->willReturn($mockAwsCloudInstanceCoordinator);


        $cloudInstanceManagementService = new CloudInstanceManagementService(
            $mockEm,
            $mockCloudInstanceCoordinatorFactory
        );


        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_SCHEDULED_FOR_TERMINATION);

        $cloudInstanceManagementService->manageCloudInstance($cloudInstance, $input, $output);


        $loglines = $output->fetch();

        $this->assertSame(CloudInstance::RUNSTATUS_TERMINATED, $cloudInstance->getRunstatus());
        $this->assertContains('instance not found at provider, setting to terminated', $loglines);
    }


    public function testScheduledForReboot()
    {
        $user = $this->getUser();
        $remoteDesktop = $this->getRemoteDesktop($user);
        $cloudInstance = $this->getCloudInstance($remoteDesktop);
        $input = $this->getInput();
        $output = new BufferedOutput();

        $mockEm = $this->getMockEntityManager(10.0, $user);

        $mockAwsCloudInstanceCoordinator = $this->getMockBuilder(AwsCloudInstanceCoordinator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAwsCloudInstanceCoordinator
            ->expects($this->once())
            ->method('triggerRebootOfCloudInstance')
            ->with($cloudInstance);

        $mockCloudInstanceCoordinatorFactory = $this->getMockCloudInstanceCoordinatorFactory();
        $mockCloudInstanceCoordinatorFactory
            ->expects($this->once())
            ->method('getCloudInstanceCoordinatorForCloudInstance')
            ->willReturn($mockAwsCloudInstanceCoordinator);

        $cloudInstanceManagementService = new CloudInstanceManagementService(
            $mockEm,
            $mockCloudInstanceCoordinatorFactory
        );


        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_SCHEDULED_FOR_REBOOT);

        $cloudInstanceManagementService->manageCloudInstance($cloudInstance, $input, $output);


        $loglines = $output->fetch();

        $this->assertSame(CloudInstance::RUNSTATUS_REBOOTING, $cloudInstance->getRunstatus());
        $this->assertContains('Action: asking the cloud instance to reboot', $loglines);
    }


    public function testRebooting()
    {
        $user = $this->getUser();
        $remoteDesktop = $this->getRemoteDesktop($user);
        $cloudInstance = $this->getCloudInstance($remoteDesktop);
        $input = $this->getInput();
        $output = new BufferedOutput();

        $mockEm = $this->getMockEntityManager(10.0, $user);

        $mockAwsCloudInstanceCoordinator = $this->getMockBuilder(AwsCloudInstanceCoordinator::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAwsCloudInstanceCoordinator
            ->expects($this->once())
            ->method('cloudInstanceIsRunning')
            ->with($cloudInstance)
            ->willReturn(true);

        $mockCloudInstanceCoordinatorFactory = $this->getMockCloudInstanceCoordinatorFactory();
        $mockCloudInstanceCoordinatorFactory
            ->expects($this->once())
            ->method('getCloudInstanceCoordinatorForCloudInstance')
            ->willReturn($mockAwsCloudInstanceCoordinator);

        $cloudInstanceManagementService = new CloudInstanceManagementService(
            $mockEm,
            $mockCloudInstanceCoordinatorFactory
        );


        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_REBOOTING);

        $cloudInstanceManagementService->manageCloudInstance($cloudInstance, $input, $output);


        $loglines = $output->fetch();

        $this->assertSame(CloudInstance::RUNSTATUS_RUNNING, $cloudInstance->getRunstatus());
        $this->assertContains('Action: probing if reboot is complete', $loglines);
    }

}
