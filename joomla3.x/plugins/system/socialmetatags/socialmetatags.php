<?php
/**
 * @package     Social Meta Tags
 * @copyright   Copyright (c) 2014 Hans Kuijpers - HKweb
 * @license     GNU General Public License version 3 or later
 */

// No direct access.
defined('_JEXEC') or die;

class PlgSystemSocialmetatags extends JPlugin
{
    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    function onBeforeRender()
    {
        // Connect to Joomla
        $app            = JFactory::getApplication();
        $doc            = JFactory::getDocument();

        if ($app->isAdmin()) {
            return;
        }

        // Detecting Active Variables
        $option         = $app->input->getCmd('option', '');
        $view           = $app->input->getCmd('view', '');
        $id             = $app->input->getCmd('id', '');
        $sitename       = $app->getCfg('sitename');
        $description    = $doc->getDescription();
        $url_site       = JURI::base();
        $url            = JURI::current();
        $title          = str_replace(' - '.$app->getCfg('sitename'), '', $doc->getTitle());
        $menu           = $app->getMenu();

        // Get Plugin info
        $params         = $this->params;
        $basicimage     = $params->get('basicimage');
        $ogtype         = 'business.business';
        $fbAdmin        = $params->get('fbadmin');

        // Component specific overrides
        if ($menu->getActive() == $menu->getDefault()) 
        {
            // overrides for homepage
        }
        elseif ($option == 'com_content' && $view == 'article')
        {
            $article            = JTable::getInstance("content");
            $article->load($id);

            $profile            = JUserHelper::getProfile($article->created_by);
            $profile_googleplus = $profile->socialmetatags['googleplus'];
            $profile_twitter    = $profile->socialmetatags['twitter'];
            $profile_facebook   = $profile->socialmetatags['facebook'];

            $user               = JFactory::getUser($article->created_by);
            $realname           = $user->name;

            if(!$description)
            {
                $description    = trim(htmlspecialchars(strip_tags($article->introtext)));
                $doc->setDescription($description);
            }

            $url                = key($doc->_links); // Key of first element form this array contains the canonical url
            $ogtype             = 'article';

            $images = json_decode($article->images);
            if($images->image_fulltext)
            {
                $basicimage = $url_site.$images->image_fulltext;
            }
            elseif ($images->image_intro)
            {
                $basicimage = $url_site.$images->image_intro;
            }
        }

        // Set Meta Tags
        $metaproperty   = array();
        $metaitemprop   = array();
        $metaname       = array();

        // Meta Tags for Discoverability
        $metaproperty['place:location:latitude'] = $params->get('placelocationlatitude');
        $metaproperty['place:location:longitude'] = $params->get('placelocationlongitude');
        $metaproperty['business:contact_data:street_address'] = $params->get('businesscontentdatastreetaddress');
        $metaproperty['business:contact_data:locality'] = $params->get('businesscontentdatalocality');
        $metaproperty['business:contact_data:postal_code'] = $params->get('businesscontentdatapostalcode');
        $metaproperty['business:contact_data:country_name'] = $params->get('businesscontentdatacountryname');
        $metaproperty['business:contact_data:email'] = $params->get('businesscontentdataemail');
        $metaproperty['business:contact_data:phone_number'] = $params->get('businesscontentdataphonenumber');
        $metaproperty['business:contact_data:website'] = $params->get('businesscontactdatawebsite');

        // Meta Tags for Google Plus
        $metaitemprop['name'] = $title;
        $metaitemprop['description'] = $description;
        $metaitemprop['image'] = $basicimage;
        if($params->get('googlepluspublisher')) {
            $doc->addHeadLink($params->get('googlepluspublisher'), 'publisher', 'rel');
        }
        if($profile_googleplus) {
            $doc->addHeadLink($profile_googleplus, 'author', 'rel');
        }

        // Meta Tags for Twitter
        $metaname['twitter:card'] = 'summary_large_image';
        $metaname['twitter:site'] = $params->get('twittersite');
        $metaname['twitter:creator'] = $profile_twitter;
        $metaname['twitter:title'] = $title;
        $metaname['twitter:description'] = $description;
        $metaname['twitter:image:src'] = $basicimage;

        // Meta Tags for Facebook
        $metaproperty['og:type'] = $ogtype;
        $metaproperty['profile:first_name'] = ''; // By default Joomla has just one field for name
        $metaproperty['profile:last_name'] = $realname;
        $metaproperty['profile:username'] = $profile_facebook;
        $metaproperty['og:title'] = $title;
        $metaproperty['og:description'] = $description;
        $metaproperty['og:image'] = $basicimage;
        $metaproperty['og:url'] = $url;
        $metaproperty['og:site_name'] = $sitename;
        $metaproperty['og:see_also'] = $url_site;
        $metaproperty['fb:admins'] = $fbAdmin;

        foreach ($metaproperty as $key => $value) {
            if ($value) {
                $doc->addCustomTag('<meta property="'.$key.'" content="'.$value.'" >');
            }
        }

        foreach ($metaitemprop as $key => $value) {
            if ($value) {
                $doc->addCustomTag('<meta itemprop="'.$key.'" content="'.$value.'" >');
            }
        }

        foreach ($metaname as $key => $value) {
            if ($value) {
                $doc->addCustomTag('<meta name="'.$key.'" content="'.$value.'" >');
            }
        }
    }
}
