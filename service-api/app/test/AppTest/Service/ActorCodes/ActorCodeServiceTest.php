<?php

declare(strict_types=1);

namespace AppTest\Service\Lpa;

use App\Service\ActorCodes\ActorCodeService;
use App\Service\Lpa\LpaService;
use App\DataAccess\Repository;
use PHPUnit\Framework\TestCase;
use DateTime;
use Prophecy\Argument;

class ActorCodeServiceTest extends TestCase
{

    /**
     * @var LpaService
     */
    private $lpaServiceProphecy;

    /**
     * @var Repository\ActorCodesInterface
     */
    private $actorCodesInterfaceProphecy;

    /**
     * @var Repository\UserLpaActorMapInterface
     */
    private $userLpaActorMapInterfaceProphecy;


    public function setUp()
    {
        $this->lpaServiceProphecy = $this->prophesize(LpaService::class);
        $this->actorCodesInterfaceProphecy = $this->prophesize(Repository\ActorCodesInterface::class);
        $this->userLpaActorMapInterfaceProphecy = $this->prophesize(Repository\UserLpaActorMapInterface::class);
    }

    private function getActorCodeService() : ActorCodeService
    {
        return new ActorCodeService(
            $this->actorCodesInterfaceProphecy->reveal(),
            $this->userLpaActorMapInterfaceProphecy->reveal(),
            $this->lpaServiceProphecy->reveal()
        );
    }


    /** @test */
    public function test_validation_with_invalid_actor_code()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn(null)->shouldBeCalled();

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertNull($result);
    }

    /** @test */
    public function test_validation_with_valid_actor_code_that_is_inactive()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => false,
        ])->shouldBeCalled();

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertNull($result);
    }

    /** @test */
    public function test_validation_with_missing_lpa()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $mockSiriusId = 'mock-id';

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => true,
            'SiriusUid' => $mockSiriusId,
        ])->shouldBeCalled();

        $this->lpaServiceProphecy->getByUid($mockSiriusId)->willReturn(null)->shouldBeCalled();

        //---

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertNull($result);
    }

    /** @test */
    public function test_validation_with_missing_actor()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $mockSiriusId = 'mock-id';

        $mockLpa = new Repository\Response\Lpa([], null);

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => true,
            'SiriusUid' => $mockSiriusId,
            'ActorLpaId' => 1,
        ])->shouldBeCalled();

        $this->lpaServiceProphecy->getByUid($mockSiriusId)->willReturn(
            $mockLpa
        )->shouldBeCalled();

        $this->lpaServiceProphecy->lookupActorInLpa(Argument::type('array'), Argument::type('int'))
            ->willReturn(null)
            ->shouldBeCalled();

        //---

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertNull($result);
    }

    /** @test */
    public function test_validation_with_invalid_actor()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => true,
            'SiriusUid' => $testUid,
            'ActorLpaId' => 1,
            'ActorCode' => 'different-actor-code'
        ])->shouldBeCalled();

        $this->lpaServiceProphecy->getByUid($testUid)->willReturn(
            new Repository\Response\Lpa([], null)
        )->shouldBeCalled();

        $this->lpaServiceProphecy->lookupActorInLpa(Argument::type('array'), Argument::type('int'))
            ->willReturn([
                'details' => ['dob' => $testDob]
            ])
            ->shouldBeCalled();

        //---

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertNull($result);
    }

    /** @test */
    public function test_validation_with_invalid_uid()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => true,
            'SiriusUid' => $testDob,
            'ActorLpaId' => 1,
            'ActorCode' => $testCode
        ])->shouldBeCalled();

        $this->lpaServiceProphecy->getByUid($testDob)->willReturn(
            new Repository\Response\Lpa([
                'uId' => 'different-uid'
            ], null)
        )->shouldBeCalled();

        $this->lpaServiceProphecy->lookupActorInLpa(Argument::type('array'), Argument::type('int'))
            ->willReturn([
                'details' => ['dob' => $testDob]
            ])
            ->shouldBeCalled();

        //---

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertNull($result);
    }

    /** @test */
    public function test_validation_with_invalid_dob()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => true,
            'SiriusUid' => $testUid,
            'ActorLpaId' => 1,
            'ActorCode' => $testCode
        ])->shouldBeCalled();

        $this->lpaServiceProphecy->getByUid($testUid)->willReturn(
            new Repository\Response\Lpa([
                'uId' => $testUid
            ], null)
        )->shouldBeCalled();

        $this->lpaServiceProphecy->lookupActorInLpa(Argument::type('array'), Argument::type('int'))
            ->willReturn([
                'details' => ['dob' => 'different-dob']
            ])
            ->shouldBeCalled();

        //---

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertNull($result);
    }

    //-------------------------------------

    /** @test */
    public function test_successful_validation()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';

        $mockLpa = [
            'uId' => $testUid
        ];

        $mockActor = [
            'details' => ['dob' => $testDob]
        ];

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => true,
            'SiriusUid' => $testUid,
            'ActorLpaId' => 1,
            'ActorCode' => $testCode
        ])->shouldBeCalled();

        $this->lpaServiceProphecy->getByUid($testUid)->willReturn(
            new Repository\Response\Lpa($mockLpa, null)
        )->shouldBeCalled();

        $this->lpaServiceProphecy->lookupActorInLpa(Argument::type('array'), Argument::type('int'))
            ->willReturn($mockActor)
            ->shouldBeCalled();

        //---

        $service = $this->getActorCodeService();

        $result = $service->validateDetails($testCode, $testUid, $testDob);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('lpa', $result);
        $this->assertArrayHasKey('actor', $result);
        $this->assertEquals($mockLpa, $result['lpa']);
        $this->assertEquals($mockActor, $result['actor']);
    }

    //-------------------------------------

    public function test_confirmation_with_invalid_details()
    {
        $service = $this->getActorCodeService();

        $result = $service->confirmDetails('test-code', 'test-uid', 'test-dob', 'test-user');

        $this->assertNull($result);
    }

    //---------------------------------

    // Setup a valid parameter set for tests from here on in.
    private function initValidParameterSet()
    {
        $testCode   = 'test-code';
        $testUid    = 'test-uid';
        $testDob    = 'test-dob';
        $testActorId    = 1;

        $mockLpa = [
            'uId' => $testUid
        ];

        $mockActor = [
            'details' => [
                'dob' => $testDob,
                'id' => $testActorId,
            ]
        ];

        $this->actorCodesInterfaceProphecy->get($testCode)->willReturn([
            'Active' => true,
            'SiriusUid' => $testUid,
            'ActorLpaId' => $testActorId,
            'ActorCode' => $testCode
        ])->shouldBeCalled();

        $this->lpaServiceProphecy->getByUid($testUid)->willReturn(
            new Repository\Response\Lpa($mockLpa, null)
        )->shouldBeCalled();

        $this->lpaServiceProphecy->lookupActorInLpa(Argument::type('array'), Argument::type('int'))
            ->willReturn($mockActor)
            ->shouldBeCalled();
    }

    //---------------------------------

    public function test_confirmation_with_valid_details()
    {
        $this->initValidParameterSet();

        $service = $this->getActorCodeService();

        $result = $service->confirmDetails('test-code', 'test-uid', 'test-dob', 'test-user');

        // We expect a uuid4 back.
        $this->assertRegExp('|^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$|', $result);
    }

}
