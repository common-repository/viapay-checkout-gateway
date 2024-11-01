<h2 id="testing">Testing</h2>

<p>Testing different scenarios in VaiPay can be done using our Test Payment Gateway and a number of test cards. The test cards are fixed but a number of predefined CVV codes and amounts can be used to trigger different scenarios. Any expiration date can be used as long as it is the current month or in the future.</p>

<h3 id="test-cards">Test cards</h3>

<table><thead>
<tr>
<th>Type</th>
<th>Id</th>
<th>Number</th>
</tr>
</thead><tbody>
<tr>
<td>Visa</td>
<td><code class="prettyprint">visa</code></td>
<td>4111 1111 1111 1111</td>
</tr>
<tr>
<td>Visa-DK</td>
<td><code class="prettyprint">visa_dk</code></td>
<td>4571 9940 0006 2336</td>
</tr>
<tr>
<td>Dankort</td>
<td><code class="prettyprint">dankort</code></td>
<td>5019 1000 0000 0006</td>
</tr>
<tr>
<td>Visa Electron</td>
<td><code class="prettyprint">visa_elec</code></td>
<td>4026 1111 1111 1115</td>
</tr>
<tr>
<td>Mastercard</td>
<td><code class="prettyprint">mc</code></td>
<td>5500 0000 0000 0004</td>
</tr>
<tr>
<td>American Express</td>
<td><code class="prettyprint">amex</code></td>
<td>3400 000000 00009</td>
</tr>
<tr>
<td>JCB</td>
<td><code class="prettyprint">jcb</code></td>
<td>3530 1113 3330 0000</td>
</tr>
<tr>
<td>Maestro</td>
<td><code class="prettyprint">maestro</code></td>
<td>6759 0000 0000 0000</td>
</tr>
<tr>
<td>Diners</td>
<td><code class="prettyprint">diners</code></td>
<td>3000 0000 0000 04</td>
</tr>
<tr>
<td>Discover</td>
<td><code class="prettyprint">discover</code></td>
<td>6011 1111 1111 1117</td>
</tr>
<tr>
<td>China Union Pay</td>
<td><code class="prettyprint">china_union_pay</code></td>
<td>6240 0086 3140 1148</td>
</tr>
<tr>
<td>Forbrugsforeningen</td>
<td><code class="prettyprint">ffk</code></td>
<td>6007 2200 0000 0004</td>
</tr>
</tbody></table>

<h3 id="recurring-token-create-errors">Recurring token create errors</h3>

<p>Errors in the authorization process of adding a recurring card payment method can triggered by the following CVV codes. To trigger additional errors for one-time charging see &ldquo;Charging errors&rdquo; below.</p>

<table><thead>
<tr>
<th>CVV</th>
<th>Scenario</th>
</tr>
</thead><tbody>
<tr>
<td><code class="prettyprint">001</code></td>
<td>The credit card is declined with due to credit card expired</td>
</tr>
<tr>
<td><code class="prettyprint">002</code></td>
<td>The credit card is declined by the acquirer</td>
</tr>
<tr>
<td><code class="prettyprint">003</code></td>
<td>The credit card is declined due to insufficient funds</td>
</tr>
<tr>
<td><code class="prettyprint">004</code></td>
<td>The authorization is declined due to errors at the acquirer</td>
</tr>
<tr>
<td><code class="prettyprint">005</code></td>
<td>The authorization is declined due to communication problems with the acquirer</td>
</tr>
<tr>
<td><code class="prettyprint">006</code></td>
<td>The authorization is declined due to communication problems with the acquirer (60 second processing time)</td>
</tr>
</tbody></table>

<h3 id="subsequent-subscription-billing-errors">Subsequent subscription billing errors</h3>

<p>All CVV codes, except the ones above, will result in a successful card authorization. The CVV codes below can be used to trigger different scenarios for subsequent payments. Declines are separated into recoverable and irrecoverable declines. Irrecoverable declines indicates that a payment will never be possible for the card again, e.g. an expired or blocked card. Recoverable declines indicates that successful payments for the card could be possible in the future. This could for example be due to insufficient funds. ViaPay will start dunning for recoverable declines, but will regularly try to process payments using the card, as long as a new payment method has not been added.</p>

<table><thead>
<tr>
<th>CVV</th>
<th>Scenario</th>
</tr>
</thead><tbody>
<tr>
<td><code class="prettyprint">100</code></td>
<td>The first payment is declined with an irrecoverable error (credit card expired)</td>
</tr>
<tr>
<td><code class="prettyprint">101</code></td>
<td>The first payment is declined with an irrecoverable error (declined by acquirer)</td>
</tr>
<tr>
<td><code class="prettyprint">102</code></td>
<td>The first payment is declined with an recoverable error (insufficient funds)</td>
</tr>
<tr>
<td><code class="prettyprint">200</code></td>
<td>The second payment is declined with an irrecoverable error (credit card expired)</td>
</tr>
<tr>
<td><code class="prettyprint">201</code></td>
<td>The second payment is declined with an irrecoverable error (declined by acquirer)</td>
</tr>
<tr>
<td><code class="prettyprint">202</code></td>
<td>The second payment is declined with an recoverable error (insufficient funds)</td>
</tr>
<tr>
<td><code class="prettyprint">300</code></td>
<td>With a probability of 50% the payment is declined with an irrecoverable error (credit card expired)</td>
</tr>
<tr>
<td><code class="prettyprint">301</code></td>
<td>With a probability of 50% the payment is declined with an irrecoverable error (declined by acquirer)</td>
</tr>
<tr>
<td><code class="prettyprint">302</code></td>
<td>With a probability of 50% the payment is declined with an recoverable error (insufficient funds)</td>
</tr>
</tbody></table>

<h3 id="charging-errors">Charging errors</h3>

<p>To trigger errors in the process of creating charges and settling authorized charges the cvv <code class="prettyprint">888</code> can be used in combination with a number of amounts. E.g. a successful authorization can be performed and then a specific amount can be used for the settlement that will result in an error. To trigger the scenarios when charging a saved card the card should be saved with cvv <code class="prettyprint">888</code>. The table below shows the amounts to be used with cvv <code class="prettyprint">888</code> to trigger errors.</p>

<table><thead>
<tr>
<th>Amount</th>
<th>Error state</th>
<th>Error code</th>
</tr>
</thead><tbody>
<tr>
<td><code class="prettyprint">1000</code></td>
<td><code class="prettyprint">success</code></td>
<td></td>
</tr>
<tr>
<td><code class="prettyprint">1001</code></td>
<td><code class="prettyprint">processing_error</code></td>
<td><code class="prettyprint">acquirer_communication_error</code></td>
</tr>
<tr>
<td><code class="prettyprint">1002</code></td>
<td><code class="prettyprint">processing_error</code></td>
<td><code class="prettyprint">acquirer_error</code></td>
</tr>
<tr>
<td><code class="prettyprint">1003</code></td>
<td><code class="prettyprint">processing_error</code></td>
<td><code class="prettyprint">acquirer_integration_error</code></td>
</tr>
<tr>
<td><code class="prettyprint">1004</code></td>
<td><code class="prettyprint">processing_error</code></td>
<td><code class="prettyprint">acquirer_authentication_error</code></td>
</tr>
<tr>
<td><code class="prettyprint">1005</code></td>
<td><code class="prettyprint">processing_error</code></td>
<td><code class="prettyprint">acquirer_configuration_error</code></td>
</tr>
<tr>
<td><code class="prettyprint">1006</code></td>
<td><code class="prettyprint">processing_error</code></td>
<td><code class="prettyprint">acquirer_rejected_error</code></td>
</tr>
<tr>
<td><code class="prettyprint">2001</code></td>
<td><code class="prettyprint">soft_declined</code></td>
<td><code class="prettyprint">insufficient_funds</code></td>
</tr>
<tr>
<td><code class="prettyprint">2002</code></td>
<td><code class="prettyprint">soft_declined</code></td>
<td><code class="prettyprint">settle_blocked</code></td>
</tr>
<tr>
<td><code class="prettyprint">3001</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">credit_card_expired</code></td>
</tr>
<tr>
<td><code class="prettyprint">3002</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">declined_by_acquirer</code></td>
</tr>
<tr>
<td><code class="prettyprint">3003</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">credit_card_lost_or_stolen</code></td>
</tr>
<tr>
<td><code class="prettyprint">3004</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">credit_card_suspected_fraud</code></td>
</tr>
<tr>
<td><code class="prettyprint">3005</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">authorization_expired</code></td>
</tr>
<tr>
<td><code class="prettyprint">3006</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">authorization_amount_exceeded</code></td>
</tr>
<tr>
<td><code class="prettyprint">3007</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">authorization_voided</code></td>
</tr>
<tr>
<td><code class="prettyprint">1337</code></td>
<td><code class="prettyprint">hard_declined</code></td>
<td><code class="prettyprint">sca_required</code> (Error for non-SCA payments to mimic PSD2 behaviour)</td>
</tr>
</tbody></table>

<h3 id="test-data-expiration">Test data expiration</h3>

<p>Old test data will automatically be deleted. Customers, and all customer releated data e.g. subscriptions and invoices, will be deleted for customers created more than three months ago.</p>