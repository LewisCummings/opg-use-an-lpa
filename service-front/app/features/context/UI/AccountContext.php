<?php

declare(strict_types=1);

namespace BehatTest\Context\UI;

use Alphagov\Notifications\Client;
use Aws\Result;
use Behat\Behat\Tester\Exception\PendingException;
use BehatTest\Context\ActorContextTrait as ActorContext;
use Fig\Http\Message\StatusCodeInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use function random_bytes;
use Exception;

require_once __DIR__ . '/../../../vendor/phpunit/phpunit/src/Framework/Assert/Functions.php';

/**
 * Class AccountContext
 *
 * @package BehatTest\Context\UI
 *
 * @property $userEmail
 * @property $userPassword
 */
class AccountContext extends BaseUIContext
{
    use ActorContext;

    /**
     * @BeforeScenario
     */
    public function seedFixtures()
    {
        // KMS is polled for encryption data on first page load
        $this->awsFixtures->append(
            new Result([
                'Plaintext' => random_bytes(32),
                'CiphertextBlob' => random_bytes(32)
            ])
        );
    }

    /**
     * @Given /^I am a user of the lpa application$/
     */
    public function iAmAUserOfTheLpaApplication()
    {
        $this->iAmOnHomepage();

        $this->clickLink('Sign in');
    }

    /**
     * @Given /^I have forgotten my password$/
     */
    public function iHaveForgottenMyPassword()
    {
        $this->assertPageAddress('/login');

        $this->clickLink('Forgotten your password?');
    }

    /**
     * @When /^I ask for my password to be reset$/
     */
    public function iAskForMyPasswordToBeReset()
    {
        $this->assertPageAddress('/forgot-password');

        // API call for password reset request
        $this->apiFixtures->patch('/v1/request-password-reset')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([ 'PasswordResetToken' => '123456' ])));

        // API call for Notify
        $this->apiFixtures->post(Client::PATH_NOTIFICATION_SEND_EMAIL)
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([])));

        $this->fillField('email', 'test@example.com');
        $this->fillField('email_confirm', 'test@example.com');
        $this->pressButton('Email me the link');
    }

    /**
     * @Then /^I receive unique instructions on how to reset my password$/
     */
    public function iReceiveUniqueInstructionsOnHowToResetMyPassword()
    {
        $this->assertPageAddress('/forgot-password');

        $this->assertPageContainsText('We\'ve emailed a link to test@example.com');

        assertEquals(true, $this->apiFixtures->isEmpty());
    }

    /**
     * @Given /^I have asked for my password to be reset$/
     */
    public function iHaveAskedForMyPasswordToBeReset()
    {
        // API fixture for reset token check
        $this->apiFixtures->get('/v1/can-password-reset')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([ 'Id' => '123456' ])));
    }

    /**
     * @When /^I follow my unique instructions on how to reset my password$/
     */
    public function iFollowMyUniqueInstructionsOnHowToResetMyPassword()
    {
        $this->visit('/forgot-password/123456');

        $this->assertPageContainsText('Change your password');
    }

    /**
     * @When /^I follow my unique expired instructions on how to reset my password$/
     */
    public function iFollowMyUniqueExpiredInstructionsOnHowToResetMyPassword()
    {
        // remove successful reset token and add failure state
        $this->apiFixtures->getHandlers()->pop();
        $this->apiFixtures->get('/v1/can-password-reset')
            ->respondWith(new Response(StatusCodeInterface::STATUS_GONE));

        $this->visit('/forgot-password/123456');
    }

    /**
     * @Given /^I choose a new password$/
     */
    public function iChooseANewPassword()
    {
        $this->assertPageAddress('/forgot-password/123456');

        // API fixture for reset token check
        $this->apiFixtures->get('/v1/can-password-reset')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([ 'Id' => '123456' ])));

        // API fixture for password reset
        $this->apiFixtures->patch('/v1/complete-password-reset')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([ 'Id' => '123456' ])))
            ->inspectRequest(function (RequestInterface $request, array $options) {
                $params = json_decode($request->getBody()->getContents(), true);

                assertInternalType('array', $params);
                assertArrayHasKey('token', $params);
                assertArrayHasKey('password', $params);
            });

        $this->fillField('password', 'n3wPassWord');
        $this->fillField('password_confirm', 'n3wPassWord');
        $this->pressButton('Change password');
    }

    /**
     * @Then /^my password has been associated with my user account$/
     */
    public function myPasswordHasBeenAssociatedWithMyUserAccount()
    {
        $this->assertPageAddress('/login');
        // TODO when flash message are in place
        //$this->assertPageContainsText('Password successfully reset');

        assertEquals(true, $this->apiFixtures->isEmpty());
    }

    /**
     * @Then /^I am told that my instructions have expired$/
     */
    public function iAmToldThatMyInstructionsHaveExpired()
    {
        $this->assertPageAddress('/forgot-password/123456');

        $this->assertPageContainsText('invalid or has expired');
    }

    /**
     * @Given /^I am unable to continue to reset my password$/
     */
    public function iAmUnableToContinueToResetMyPassword()
    {
        // Not needed for this context
    }

    /**
     * @Given /^I choose a new invalid password of "(.*)"$/
     */
    public function iChooseANewInvalid($password)
    {
        $this->assertPageAddress('/forgot-password/123456');

        // API fixture for reset token check
        $this->apiFixtures->get('/v1/can-password-reset')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([ 'Id' => '123456' ])));

        $this->fillField('password', $password);
        $this->fillField('password_confirm', $password);
        $this->pressButton('Change password');
    }

    /**
     * @Then /^I am told that my password is invalid because it needs at least (.*)$/
     */
    public function iAmToldThatMyPasswordIsInvalidBecauseItNeedsAtLeast($reason)
    {
        $this->assertPageAddress('/forgot-password/123456');

        $this->assertPageContainsText('at least ' . $reason);
    }

    /**
     * @Given /^I am signed in$/
     */
    public function iSignIn()
    {
        $this->userEmail = 'test@test.com';
        $this->userPassword = 'pa33w0rd';

        $this->visit('/login');
        $this->assertPageAddress('/login');
        $this->assertPageContainsText('Continue');

        // API call for password reset request
        $this->apiFixtures->patch('/v1/auth')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([
                'Id'        => '123',
                'Email'     => $this->userEmail,
                'LastLogin' => null
            ])));

        // Dashboard page checks for all LPA's for a user
        $this->apiFixtures->get('/v1/lpas')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([])));

        $this->fillField('email', $this->userEmail);
        $this->fillField('password', $this->userPassword);

        $this->pressButton('Continue');

        // ---

        $this->assertPageAddress('/lpa/add-details');
    }

    /**
     * @When /^I view my user details$/
     */
    public function iViewMyUserDetails()
    {
        $this->visit('/your-details');
        $this->assertPageContainsText('Your details');
    }

    /**
     * @Then /^I can change my email if required$/
     */
    public function iCanChangeMyEmailIfRequired()
    {
        $this->assertPageAddress('/your-details');
        
        $this->assertPageContainsText('Email address');
        $this->assertPageContainsText($this->userEmail);

        $session = $this->getSession();
        $page = $session->getPage();

        $changeEmailText = 'Change email address';
        $link = $page->findLink($changeEmailText);
        if ($link === null) {
            throw new \Exception($changeEmailText . ' link not found');
        }
    }

    /**
     * @Then /^I can change my passcode if required$/
     */
    public function iCanChangeMyPasscodeIfRequired()
    {
        $this->assertPageAddress('/your-details');

        $this->assertPageContainsText('Password');

        $session = $this->getSession();
        $page = $session->getPage();

        $changePasswordtext = "Change password";
        $link = $page->findLink($changePasswordtext);
        if ($link === null) {
            throw new \Exception($changePasswordtext . ' link not found');
        }
    }

    /**
     * @When /^I ask for a change of donors or attorneys details$/
     */
    public function iAskForAChangeOfDonorsOrAttorneysDetails()
    {
        $this->assertPageAddress('/your-details');

        $this->assertPageContainsText('Change a donor\'s or attorney\'s details');
        $this->clickLink('Change a donor\'s or attorney\'s details');
    }

    /**
     * @Then /^Then I am given instructions on how to change donor or attorney details$/
     */
    public function iAmGivenInstructionOnHowToChangeDonorOrAttorneyDetails()
    {
        $this->assertPageAddress('/lpa/change-details');

        $this->assertPageContainsText('Let us know if a donor\'s or attorney\'s details change');
        $this->assertPageContainsText('Find out more');
    }

    /**
     * @Given /^I am on the add an LPA page$/
     */
    public function iAmOnTheAddAnLPAPage()
    {
        $this->visit('/lpa/add-details');
        $this->assertPageAddress('/lpa/add-details');
    }

    /**
     * @When /^I request to add an LPA with valid details$/
     */
    public function iRequestToAddAnLPAWithValidDetails()
    {
        $this->assertPageAddress('/lpa/add-details');

        // API call for adding an LPA
        $this->apiFixtures->post('/v1/actor-codes/summary')
            ->respondWith(new Response(StatusCodeInterface::STATUS_OK, [], json_encode([
                "donor" => [
                    "id"=> 23,
                    "uId"=> "7000-0000-0971",
                    "dob"=> "1948-11-01",
                    "email"=> "babaragilson@opgtest.com",
                    "salutation"=> "Mrs",
                    "firstname"=> "Babara",
                    "middlenames"=> "Suzanne",
                    "surname"=> "Gilson",
                    "addresses"=> [
                    [
                        "id"=> 23,
                      "town"=> "",
                      "county"=> "",
                      "postcode"=> "HS8 2YB",
                      "country"=> "",
                      "type"=> "Primary",
                      "addressLine1"=> "24 Gloucester Road",
                      "addressLine2"=> "CILLE BHRIGHDE",
                      "addressLine3"=> ""
                    ]
                  ],
                  "companyName"=> null,
                  "systemStatus"=> true
    ]
            ])));

        $this->fillField('passcode', 'XYUPHWQRECHV');
        $this->fillField('reference_number', '700000000138');
        $this->fillField('dob[day]', '05');
        $this->fillField('dob[month]', '10');
        $this->fillField('dob[year]', '1975');
        $this->pressButton('Continue');
    }

    /**
     * @Then /^My LPA is successfully added$/
     */
    public function myLPAIsSuccessfullyAdded()
    {
        $this->assertPageAddress('/lpa/check');

        $test = $this->getSession()->getPage()->find('css', 'div>div>h1')->getText();
        //$this->assertPageContainsText('We could not find that lasting power of attorney');
        //$this->assertPageContainsText('Is this the LPA you want to add?');

        $expectedDonor = $this->getSession()->getPage()->find('css', 'dl>div>dd')->getText();
        $actualDonor = 'Mrs Babara Suzanne Gilson';

        if ($expectedDonor !== $actualDonor) {
            throw new Exception('LPA found for ' . $actualDonor . ' rather than ' . $expectedDonor);
        }

        $this->pressButton('Continue');

    }

    /**
     * @Given /^My LPA appears on the dashboard$/
     */
    public function myLPAAppearsOnTheDashboard()
    {
        $this->assertPageAddress('/lpa/dashboard');

        $expectedDonor = $this->getSession()->getPage()->find('css', 'div>div>span')->getText();
        $actualDonor = 'Babara Suzanne Gilson';

        if ($expectedDonor !== $actualDonor) {
            throw new Exception('LPA found for ' . $actualDonor . ' rather than ' . $expectedDonor);
        }
    }

}