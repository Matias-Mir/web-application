<?php

namespace Tests\Functional;

use AppBundle\Entity\CloudInstance\CloudInstance;
use AppBundle\Entity\RemoteDesktop\Event\RemoteDesktopRelevantForBillingEvent;
use AppBundle\Entity\RemoteDesktop\RemoteDesktop;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Helpers\Helpers;

class RemoveRemoteDesktopsFunctionalTest extends WebTestCase
{
    use Helpers;

    protected function verifyDektopStatusRemoving(Client $client, Crawler $crawler)
    {
        $link = $crawler->selectLink('Refresh status')->first()->link();
        $crawler = $client->click($link);

        $this->assertContains('My first cloud gaming rig', $crawler->filter('h2')->first()->text());

        $this->assertContains('Usage costs per hour', $crawler->filter('div.usagecostsforoneintervalbox')->first()->text());
        $this->assertContains('(only in status Ready to use and Rebooting): $1.95', $crawler->filter('div.usagecostsforoneintervalbox')->first()->text());

        $this->assertContains('Storage costs per hour', $crawler->filter('div.usagecostsforoneintervalbox')->first()->text());
        $this->assertContains('(until rig is removed): $0.04', $crawler->filter('div.usagecostsforoneintervalbox')->first()->text());

        $this->assertContains('Current status:', $crawler->filter('h3')->first()->text());
        $this->assertContains('Removing...', $crawler->filter('.remotedesktopstatus')->first()->text());

        $this->assertContains('Refresh status', $crawler->filter('.panel-footer a.btn')->first()->text());
        $this->assertEquals(
            1,
            $crawler->filter('.panel-footer a.btn')->count()
        );
    }

    public function testRemoveNotYetLaunchedRemoteDesktop()
    {
        $client = (new CreateRemoteDesktopFunctionalTest())->testCreateRemoteDesktop();

        $crawler = $client->request('GET', '/en/remoteDesktops/');

        $link = $crawler->selectLink('Remove this cloud gaming rig')->first()->link();

        $client->click($link);

        $crawler = $client->followRedirect();

        // We want to be back in the overview
        $this->assertEquals(
            '/en/remoteDesktops/',
            $client->getRequest()->getRequestUri()
        );

        // Because it was never launched, the desktop is immediately gone

        $this->assertEmpty($crawler->filter('h2'));
        $this->assertEmpty($crawler->filter('div.usagecostsforoneintervalbox'));
        $this->assertEmpty($crawler->filter('h3'));
        $this->assertEmpty($crawler->filter('.remotedesktopstatus'));

        $this->assertEquals(
            0,
            $crawler->filter('.panel-footer a.btn')->count()
        );
    }

    public function testRemoveStoppedRemoteDesktop()
    {
        $client = (new StopRemoteDesktopFunctionalTest())->testStopRemoteDesktop();

        $crawler = $client->request('GET', '/en/remoteDesktops/');

        $link = $crawler->selectLink('Remove this cloud gaming rig')->first()->link();

        $client->click($link);

        $crawler = $client->followRedirect();

        // We want to be back in the overview
        $this->assertEquals(
            '/en/remoteDesktops/',
            $client->getRequest()->getRequestUri()
        );


        // At this point, the instance is in "Scheduled for termination" state

        $this->verifyDektopStatusRemoving($client, $crawler);


        // Switching to "Terminating" status, which must not change the desktop status

        $container = $client->getContainer();
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $remoteDesktopRepo = $em->getRepository('AppBundle\Entity\RemoteDesktop\RemoteDesktop');
        /** @var RemoteDesktop $remoteDesktop */
        $remoteDesktop = $remoteDesktopRepo->findOneBy(['title' => 'My first cloud gaming rig']);
        /** @var CloudInstance $cloudInstance */
        $cloudInstance = $remoteDesktop->getCloudInstances()->get(0);
        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_TERMINATING);
        $em->persist($cloudInstance);
        $em->flush();

        $this->verifyDektopStatusRemoving($client, $crawler);


        // Switching instance to "Terminated" status, which must put the desktop into "Removed" status

        $remoteDesktop = $remoteDesktopRepo->findOneBy(['title' => 'My first cloud gaming rig']);
        /** @var CloudInstance $cloudInstance */
        $cloudInstance = $remoteDesktop->getCloudInstances()->get(0);
        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_TERMINATED);
        $em->persist($cloudInstance);
        $em->flush();

        $link = $crawler->selectLink('Refresh status')->first()->link();
        $crawler = $client->click($link);

        $this->assertEmpty($crawler->filter('h2'));
        $this->assertEmpty($crawler->filter('div.usagecostsforoneintervalbox'));
        $this->assertEmpty($crawler->filter('h3'));
        $this->assertEmpty($crawler->filter('.remotedesktopstatus'));

        $this->assertEquals(
            0,
            $crawler->filter('.panel-footer a.btn')->count()
        );
    }

    public function testRemoveRunningRemoteDesktop()
    {
        $client = (new LaunchRemoteDesktopFunctionalTest())->testLaunchRemoteDesktop();

        $crawler = $client->request('GET', '/en/remoteDesktops/');

        $link = $crawler->selectLink('Remove this cloud gaming rig')->first()->link();

        $client->click($link);

        $crawler = $client->followRedirect();

        // We want to be back in the overview
        $this->assertEquals(
            '/en/remoteDesktops/',
            $client->getRequest()->getRequestUri()
        );


        // At this point, the instance is in "Scheduled for termination" state

        $this->verifyDektopStatusRemoving($client, $crawler);


        $container = $client->getContainer();
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $remoteDesktopRelevantForBillingEventRepo = $em->getRepository(RemoteDesktopRelevantForBillingEvent::class);

        /** @var RemoteDesktopRelevantForBillingEvent[] $remoteDesktopRelevantForBillingEvents */
        $remoteDesktopRelevantForBillingEvents = $remoteDesktopRelevantForBillingEventRepo->findAll();

        $this->assertEquals(
            4, // provisioned, available, unprovisioned, unavailable
            sizeof($remoteDesktopRelevantForBillingEvents)
        );

        $this->assertEquals(
            RemoteDesktopRelevantForBillingEvent::EVENT_TYPE_DESKTOP_WAS_PROVISIONED_FOR_USER,
            $remoteDesktopRelevantForBillingEvents[0]->getEventType()
        );

        $this->assertEquals(
            RemoteDesktopRelevantForBillingEvent::EVENT_TYPE_DESKTOP_BECAME_AVAILABLE_TO_USER,
            $remoteDesktopRelevantForBillingEvents[1]->getEventType()
        );

        $this->assertEquals(
            RemoteDesktopRelevantForBillingEvent::EVENT_TYPE_DESKTOP_BECAME_UNAVAILABLE_TO_USER,
            $remoteDesktopRelevantForBillingEvents[2]->getEventType()
        );

        $this->assertEquals(
            RemoteDesktopRelevantForBillingEvent::EVENT_TYPE_DESKTOP_WAS_UNPROVISIONED_FOR_USER,
            $remoteDesktopRelevantForBillingEvents[3]->getEventType()
        );


        // Switching to "Terminating" status, which must not change the desktop status

        $container = $client->getContainer();
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $remoteDesktopRepo = $em->getRepository('AppBundle\Entity\RemoteDesktop\RemoteDesktop');
        /** @var RemoteDesktop $remoteDesktop */
        $remoteDesktop = $remoteDesktopRepo->findOneBy(['title' => 'My first cloud gaming rig']);
        /** @var CloudInstance $cloudInstance */
        $cloudInstance = $remoteDesktop->getCloudInstances()->get(0);
        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_TERMINATING);
        $em->persist($cloudInstance);
        $em->flush();

        $this->verifyDektopStatusRemoving($client, $crawler);


        // Switching instance to "Terminated" status, which must put the desktop into "Removed" status

        $remoteDesktop = $remoteDesktopRepo->findOneBy(['title' => 'My first cloud gaming rig']);
        /** @var CloudInstance $cloudInstance */
        $cloudInstance = $remoteDesktop->getCloudInstances()->get(0);
        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_TERMINATED);
        $em->persist($cloudInstance);
        $em->flush();

        $link = $crawler->selectLink('Refresh status')->first()->link();
        $crawler = $client->click($link);

        $this->assertEmpty($crawler->filter('h2'));
        $this->assertEmpty($crawler->filter('div.usagecostsforoneintervalbox'));
        $this->assertEmpty($crawler->filter('h3'));
        $this->assertEmpty($crawler->filter('.remotedesktopstatus'));

        $this->assertEquals(
            0,
            $crawler->filter('.panel-footer a.btn')->count()
        );
    }

}
