<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Swiftmailer\Transport;

use Mautic\CoreBundle\Factory\MauticFactory;

use Symfony\Component\HttpFoundation\Request;
use Mautic\LeadBundle\Entity\DoNotContact;

/**
 * Class MailjetlTransport
 */
class MailjetTransport extends AbstractTokenSmtpTransport implements InterfaceCallbackTransport
{

	/**
	 * {@inheritdoc}
	 */
	public function __construct($host = 'localhost', $port = 25, $security = null)
	{
		parent::__construct('in-v3.mailjet.com', 587, 'tls');
		$this->setAuthMode('login');
	}


	/**
	 * Return the max number of to addresses allowed per batch.  If there is no limit, return 0
	 *
	 * @return int
	 */
	public function getMaxBatchLimit(){
		// not use with mailjet
		return 0;
	}

	/**
	 * Get the count for the max number of recipients per batch
	 *
	 * @param \Swift_Message $message
	 * @param int            $toBeAdded Number of emails about to be added
	 * @param string         $type      Type of emails being added (to, cc, bcc)
	 *
	 * @return mixed
	 */
	public function getBatchRecipientCount(\Swift_Message $message, $toBeAdded = 1, $type = 'to'){
		// not use with mailjet
		return 0;
	}


	/**
	 * Do whatever is necessary to $this->message in order to deliver a batched payload. i.e. add custom headers, etc
	 *
	 * @return void
	 */
	protected function prepareMessage(){

	//add leadIdHash to track this email
		if (isset($this->message->leadIdHash)){
			$this->message->getHeaders()->addTextHeader('X-MJ-CUSTOMID',$this->message->leadIdHash);
		}
	}

    /**
     * Returns a "transport" string to match the URL path /mailer/{transport}/callback
     *
     * @return mixed
     */
    public function getCallbackPath()
    {
        return 'mailjet';
    }

    /**
     * Handle response
     *
     * @param Request       $request
     * @param MauticFactory $factory
     *
     * @return mixed
     */
    public function handleCallbackResponse(Request $request, MauticFactory $factory)
    {
		$postData = json_decode($request->getContent(), true);
	   	$rows = array (
				'bounced' => array (
						'hashIds' => array (),
						'emails' => array ()
				),
				'unsubscribed' => array (
						'hashIds' => array (),
						'emails' => array ()
				)
		);

		if (is_array ( $postData ) && isset ( $postData ['event'] )) {
			// Mailjet API callback V1
			$events = array (
					$postData
			);
		} elseif (is_array ( $postData )) {
			// Mailjet API callback V2
			$events = $postData;
		} else {
			// respone must be an array
			return null;
		}

		foreach ( $events as $event ) {
			if (in_array ( $event ['event'], array (
					'bounce',
					'blocked',
					'spam',
					'unsub'
			) )) {
				if ($event ['event'] === 'bounce' || $event ['event'] === 'blocked') {
					$reason = $event ['error_related_to'] . ' : '. $event ['error'];
					$type = DoNotContact::BOUNCED;
				} elseif ($event ['event'] === 'spam') {
					$reason = 'User reported email as spam, source :' . $event ['source'];
					$type = DoNotContact::BOUNCED;
				} elseif ($event ['event'] === 'unsub') {
					$reason = 'User unsubscribed';
					$type = DoNotContact::UNSUBSCRIBED;
				}

				if (isset ( $event ['CustomID'] ) && $event ['CustomID']!=='') {
					$rows [$type] ['hashIds'] [$event ['CustomID']] = $reason;
				} else {
					$rows [$type] ['emails'] [$event ['email']] = $reason;
				}
			}
		}
		return $rows;
    }


}