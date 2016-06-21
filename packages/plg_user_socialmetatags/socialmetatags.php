<?php
/**
 * @package     Plugin Social Meta Tags for Joomla! 3.4
 * @author      Hans Kuijpers
 * @copyright   (C) 2014 Hans Kuijpers - HKweb
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

// No direct access.
defined('_JEXEC') or die;

class plgUserSocialmetatags extends JPlugin
{
    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * @param   string  $context    The context for the data
     * @param   int     $data       The user id
     * @param   object
     *
     * @return    boolean
     */
    public function onContentPrepareData($context, $data)
    {
        // Check we are manipulating a valid form.
        if (!in_array($context, array('com_users.profile', 'com_users.user', 'com_users.registration', 'com_admin.profile')))
        {
            return true;
        }

        if (is_object($data))
        {
            $userId = isset($data->id) ? $data->id : 0;

            if (!isset($data->profile) and $userId > 0)
            {

                // Load the profile data from the database.
                $db = JFactory::getDbo();

                $query = $db->getQuery(true)
                    ->select(array('profile_key', 'profile_value'))
                    ->from('#__user_profiles')
                    ->where('user_id = ' . $db->q($userId))
                    ->where('profile_key LIKE ' . $db->q('socialmetatags.%'))
                    ->order('ordering');
                $db->setQuery($query);

                try
                {
                    $results = $db->loadRowList();

                    if ($db->getErrorNum())
                    {
                        $this->_subject->setError($db->getErrorMsg());

                        return false;
                    }
                }
                catch (Exception $exc)
                {
                    $this->_subject->setError($exc->getMessage());

                    return false;
                }

                // Merge the profile data.
                $data->socialmetatags = array();

                foreach ($results as $v)
                {
                    $k = str_replace('socialmetatags.', '', $v[0]);
                    $data->socialmetatags[$k] = $v[1];
                }
            }
        }

        return true;
    }

    /**
     * @param   JForm   $form   The form to be altered.
     * @param   array   $data   The associated data for the form.
     *
     * @return    boolean
     */
    public function onContentPrepareForm($form, $data)
    {

        if (!($form instanceof JForm))
        {
            $this->_subject->setError('JERROR_NOT_A_FORM');

            return false;
        }

        // Check we are manipulating a valid form.
        if (!in_array($form->getName(), array('com_admin.profile', 'com_users.user', 'com_users.registration', 'com_users.profile')))
        {
            return true;
        }

        // Add the registration fields to the form.
        JForm::addFormPath(dirname(__FILE__) . '/form');

        $form->loadFile('socialmetatags', false);

        return true;
    }

    public function onUserAfterSave($data, $isNew, $result, $error)
    {
        $userId = JArrayHelper::getValue($data, 'id', 0, 'int');

        if ($userId && $result && isset($data['socialmetatags']) && (count($data['socialmetatags'])))
        {
            try
            {
                $db = JFactory::getDbo();
                $query = $db->getQuery(true)
                    ->delete('#__user_profiles')
                    ->where('user_id = ' . $db->q($userId))
                    ->where('profile_key LIKE ' . $db->q('socialmetatags.%'));
                $db->setQuery($query);

                if (!$db->execute())
                {
                    throw new Exception($db->getErrorMsg());
                }

                $tuples = array();
                $order = 1;

                foreach ($data['socialmetatags'] as $k => $v)
                {
                    $tuples[] = '(' . $userId . ', ' . $db->quote('socialmetatags.' . $k) . ', ' . $db->quote($v) . ', ' . $order++ . ')';
                }

                $query = $db->getQuery(true);
                $db->setQuery('INSERT INTO #__user_profiles VALUES ' . implode(', ', $tuples));

                if (!$db->execute())
                {
                    throw new Exception($db->getErrorMsg());
                }
            }
            catch (JException $e)
            {
                $this->_subject->setError($e->getMessage());

                return false;
            }
        }

        return true;
    }

    /**
     * Remove all user profile information for the given user ID
     *
     * Method is called after user data is deleted from the database
     *
     * @param   array   $user       Holds the user data
     * @param   boolean $success    True if user was succesfully stored in the database
     * @param   string  $msg        Message
     */
    public function onUserAfterDelete($user, $success, $msg)
    {
        if (!$success)
        {
            return false;
        }

        $userId = JArrayHelper::getValue($user, 'id', 0, 'int');

        if ($userId)
        {
            try
            {
                $db = JFactory::getDbo();
                $query = $db->getQuery(true)
                    ->delete('#__user_profiles')
                    ->where('user_id = ' . $db->q($userId))
                    ->where('profile_key LIKE ' . $db->q('socialmetatags.%'));
                $db->setQuery($query);

                if (!$db->execute())
                {
                    throw new Exception($db->getErrorMsg());
                }
            }
            catch (JException $e)
            {
                $this->_subject->setError($e->getMessage());
                return false;
            }
        }

        return true;
    }

}
