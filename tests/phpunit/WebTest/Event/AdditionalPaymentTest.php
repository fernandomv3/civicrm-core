<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.5                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

require_once 'CiviTest/CiviSeleniumTestCase.php';

/**
 * Class WebTest_Event_AdditionalPaymentTest
 */
class WebTest_Event_AdditionalPaymentTest extends CiviSeleniumTestCase {
  protected function setUp() {
    parent::setUp();
  }

  // CRM-13964 and CRM-13965
  function testParticipantParitalPaymentInitiation() {
    // Log in using webtestLogin() method
    $this->webtestLogin();

    $firstName = substr(sha1(rand()), 0, 7);
    $this->webtestAddContact($firstName, 'Anderson', TRUE);
    $contactName = "Anderson, $firstName";
    $displayName = "$firstName Anderson";

    $this->openCiviPage("participant/add", "reset=1&action=add&context=standalone", "_qf_Participant_upload-bottom");

    // Type contact last name in contact auto-complete, wait for dropdown and click first result
    $this->webtestFillAutocomplete($firstName);

    // Select event. Based on label for now.
    $this->select2('event_id', "Rain-forest Cup Youth Soccer Tournament");

    // Select role
    $this->select('role_id', "value=2");

    // Choose Registration Date.
    // Using helper webtestFillDate function.
    $this->webtestFillDate('register_date', 'now');
    $today = date('F jS, Y', strtotime('now'));
    // May 5th, 2010

    // Select participant status
    $this->select('status_id', 'value=1');

    // Setting registration source
    $this->type('source', 'Event Partially Paid Webtest');

    // Since we're here, let's check of screen help is being displayed properly
    $this->assertTrue($this->isTextPresent('Source for this registration (if applicable).'));

    // Select an event fee
    $this->waitForElementPresent('priceset');

    $this->click("xpath=//input[@class='crm-form-radio']");
    sleep(1);
    // record payment total amount
    // amount populated after fee selection
    $amtTotalOwed = (int) $this->getValue('id=total_amount');
    $this->assertEquals($amtTotalOwed, 800, 'The amount owed doesn\'t match to fee amount selected');

    // now change the amount to lesser amount value
    $this->type('total_amount', '400');

    // Select payment method = Check and enter chk number
    $this->select('payment_instrument_id', 'value=4');
    $this->waitForElementPresent('check_number');
    $this->type('check_number', '1044');

    // give some time for js to process
    sleep(1);
    $this->verifySelectedLabel("status_id", 'Partially paid');

    // later on change the status
    $this->select('status_id', 'value=1');

    // Clicking save.
    // check for proper info message displayed regarding status
    $this->chooseCancelOnNextConfirmation();
    $this->click('_qf_Participant_upload-bottom');
    $this->assertTrue((bool)preg_match("/Payment amount is less than the amount owed. Expected participant status is 'Partially paid'. Are you sure you want to set the participant status to Registered/", $this->getConfirmation()));

    // select partially paid status again and click on save
    $this->select('status_id', 'label=Partially paid');

    // Clicking save.
    $this->click('_qf_Participant_upload-bottom');
    $this->waitForPageToLoad($this->getTimeoutMsec());

    // Is status message correct?
    $this->waitForText('crm-notification-container', "Event registration for $displayName has been added");
    $this->waitForElementPresent("xpath=//form[@id='Search']//table//tbody/tr[1]/td[8]/span/a[text()='View']");
    //click through to the participant view screen
    $this->click("xpath=//form[@id='Search']//table//tbody/tr[1]/td[8]/span/a[text()='View']");
    $this->waitForElementPresent('_qf_ParticipantView_cancel-bottom');

    $this->webtestVerifyTabularData(
      array(
        'Event' => 'Rain-forest Cup Youth Soccer Tournament',
        'Participant Role' => 'Volunteer',
        'Status' => 'Partially paid',
        'Event Source' => 'Event Partially Paid Webtest',
      )
    );

    // check the fee amount and contribution amount
    $this->_checkPaymentInfoTable(800.00, 400.00);
    $balance = 800.00 - 400.00;
    //click through to the contribution view screen
    $this->click("xpath=id('ParticipantView')/div[2]/table[@class='selector row-highlight']/tbody/tr[1]/td[8]/span/a[text()='View']");
    $this->waitForElementPresent('_qf_ContributionView_cancel-bottom');

    $this->webtestVerifyTabularData(
      array(
        'From' => $displayName,
        'Financial Type' => 'Event Fee',
        'Total Amount' => '$ 800.00',
        'Contribution Status' => 'Partially paid',
        'Paid By' => 'Check',
        'Check Number' => '1044',
      )
    );

    $this->click('_qf_ContributionView_cancel-top');
    $this->waitForElementPresent("xpath=id('ParticipantView')/div[2]/table[@class='selector row-highlight']/tbody/tr[1]/td[8]/span/a[text()='View']");
    // make additional payment
    // 1 - check for links presence on participant view and edit page
    $this->assertElementPresent("xpath=id('Search')/table[@class='selector row-highlight']/tbody/tr[1]/td[8]/span[2]/ul/li[2]/a[text()='Record Payment']");
    $this->click("xpath=id('Search')/table[@class='selector row-highlight']/tbody/tr[1]/td[8]/span/a[text()='View']");
    $this->waitForElementPresent("xpath=id('ParticipantView')");
    $this->assertElementPresent("xpath=id('ParticipantView')//td[@id='payment-info']/a/span[contains(text(), 'Record Payment')]");

    $this->click("xpath=id('ParticipantView')//div[@class='action-link']/div/a/span[contains(text(), 'Edit')]");
    $this->waitForElementPresent("xpath=id('Participant')");
    $this->assertElementPresent("xpath=id('Participant')//td[@id='payment-info']//a/span[contains(text(), 'Record Payment')]");
    $location = $this->getAttribute("xpath=id('Participant')//td[@id='payment-info']/a/span[contains(text(), 'Record Payment')]/../@href");

    $this->open($location);
    $this->waitForElementPresent("xpath=id('AdditionalPayment')");
    $this->assertElementContainsText("xpath=id('AdditionalPayment')/h3", 'New Event Payment');

    // verify balance
    $text = $this->getText("xpath=id('AdditionalPayment')/div[2]//table/tbody/tr[3]/td[2]");
    $this->assertTrue((bool)preg_match("/{$balance}/", $text));

    // check form rule error
    $errorBalance = $balance + 1;
    $this->type('total_amount', $errorBalance);
    $this->select('payment_instrument_id', 'label=Cash');
    $this->click('_qf_AdditionalPayment_upload-bottom');
    $this->waitForText("xpath=//span[@id='totalAmount']/span", 'Payment amount cannot be greater than owed amount');
    $this->type('total_amount', $balance);
    $this->click('_qf_AdditionalPayment_upload-bottom');
    $this->waitForText('crm-notification-container', 'The payment record has been processed.');

    $this->waitForElementPresent("xpath=id('Search')/table[@class='selector row-highlight']/tbody/tr[1]/td[8]/span/a[text()='View']");
    $this->click("xpath=id('Search')/table[@class='selector row-highlight']/tbody/tr[1]/td[8]/span/a[text()='View']");
    $this->waitForElementPresent("_qf_ParticipantView_cancel-top");

    $this->webtestVerifyTabularData(
      array(
        'Event' => 'Rain-forest Cup Youth Soccer Tournament',
        'Participant Role' => 'Volunteer',
        'Status' => 'Registered',
        'Event Source' => 'Event Partially Paid Webtest',
      )
    );
    // check the fee amount and contribution amount
    $this->_checkPaymentInfoTable(800.00, 800.00);

    // check for not apprence of record payment button
    $this->assertFalse($this->isElementPresent("xpath=id('ParticipantView')//td[@id='payment-info']//a/span[contains(text(), 'Record Payment')]"));

    $this->click("xpath=id('ParticipantView')/div[2]/table[@class='selector row-highlight']/tbody/tr[1]/td[8]/span/a[text()='View']");
    $this->waitForElementPresent('_qf_ContributionView_cancel-bottom');

    $this->webtestVerifyTabularData(
      array(
        'From' => $displayName,
        'Financial Type' => 'Event Fee',
        'Total Amount' => '$ 800.00',
        'Contribution Status' => 'Completed',
        'Paid By' => 'Check',
        'Check Number' => '1044',
      )
    );
    $this->click('_qf_ContributionView_cancel-bottom');

    // view transaction popup info check
    $this->waitForElementPresent("xpath=//td[@id='payment-info']/table[@id='info']/tbody/tr[2]/td[2]/a");
    $this->click("xpath=//td[@id='payment-info']/table[@id='info']/tbody/tr[2]/td[2]/a");
    $this->waitForElementPresent("xpath=//table[@id='info']/tbody/tr/th[contains(text(), 'Amount')]/../../tr[2]/td[contains(text(), '$ 400.00')]/../../tr[3]/td[contains(text(), '$ 400.00')]");
    $this->waitForElementPresent("xpath=//table[@id='info']/tbody/tr/th[3][contains(text(), 'Paid By')]/../../tr[2]/td[3][contains(text(), 'Check')]/../../tr[3]/td[3][contains(text(), 'Cash')]");
    $this->waitForElementPresent("xpath=//table[@id='info']/tbody/tr/th[6][contains(text(), 'Status')]/../../tr[2]/td[6][contains(text(), 'Completed')]/../../tr[3]/td[6][contains(text(), 'Completed')]");
  }

  /**
   * @param $feeAmt
   * @param $amtPaid
   */
  function _checkPaymentInfoTable($feeAmt, $amtPaid) {
    $this->assertElementContainsText("xpath=//td[@id='payment-info']/table[@id='info']/tbody/tr[2]/td", "$ {$feeAmt}", 'Missing text: appropriate fee amount');
    $this->assertElementContainsText("xpath=//td[@id='payment-info']/table[@id='info']/tbody/tr[2]/td[2]", "$ {$amtPaid}", 'Missing text: appropriate fee amount');
  }
}
