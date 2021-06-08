<?php

/**
 * Abstract Medium Controller.
 *
 * @category   Anahita
 *
 * @author     Arash Sanieyan <ash@anahitapolis.com>
 * @author     Rastin Mehr <rastin@anahitapolis.com>
 * @license    GNU GPLv3 <http://www.gnu.org/licenses/gpl-3.0.html>
 *
 * @link       http://www.GetAnahita.com
 */
abstract class ComMediumControllerAbstract extends ComBaseControllerService
{
    /**
     * Constructor.
     *
     * @param AnConfig $config An optional AnConfig object with configuration options.
     */
    public function __construct(AnConfig $config)
    {
        parent::__construct($config);

        $this->registerCallback(array(
            'after.add'),
            array($this, 'createStoryCallback'));

        //add medium related states
        $this->getState()->insert('filter')->insert('grid')->insert('order');

        $this->registerCallback(array(
            'after.delete',
            'after.add', ),
            array($this, 'redirect'));
    }

    /**
     * Initializes the default configuration for the object.
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param AnConfig $config An optional AnConfig object with configuration options.
     */
    protected function _initialize(AnConfig $config)
    {
        $config->append(array(
            'state' => array(
                'viewer' => get_viewer(),
             ),
             'request' => array(
                'filter' => null,
                'order' => null,
            ),
            'behaviors' => array(
                'com:search.controller.behavior.searchable',
                'com:stories.controller.behavior.publisher',
                'com:notifications.controller.behavior.notifier',
                'composable',
                'commentable',
                'votable',
                'privatable',
                'subscribable',
                'com:hashtags.controller.behavior.hashtaggable',
                'com:locations.controller.behavior.geolocatable',
                'com:people.controller.behavior.mentionable',
                'ownable',
        ), ));

        parent::_initialize($config);
    }

    /**
     * Browse Action.
     *
     * @param AnCommandContext $context Context Parameter
     *
     * @return AnDomainQuery
     */
    protected function _actionBrowse(AnCommandContext $context)
    {
        $entities = parent::_actionBrowse($context);

        if (
            $this->getRepository()->hasBehavior('ownable') &&
            $this->filter == 'leaders'
        ) {
            $leaderIds = array($this->viewer->id);
            $leaderIds = array_merge($leaderIds, $this->viewer->getLeaderIds()->toArray());
            $entities->where('owner.id', 'IN', $leaderIds);
        } elseif (
            $this->getRepository()->hasBehavior('ownable') &&
            $this->actor && $this->actor->id > 0
        ) {
            $entities->where('owner', '=', $this->actor);
        }

        return $entities;
    }

    /**
     * Set the necessary redirect.
     *
     * @param AnCommandContext $context
     */
    public function redirect(AnCommandContext $context)
    {
        $url['view'] = AnInflector::pluralize($this->getIdentifier()->name);
        $url['option'] = $this->getIdentifier()->package;

        if ($context->action == 'add') {
            $url['id'] = $this->getItem()->id;
        } elseif ($context->action == 'delete') {
            $url['oid'] = $this->getItem()->owner->id;
        }

        $this->getResponse()->setRedirect(route($url));
    }

    /**
     * Set the default Actor View.
     *
     * @param AnCommandContext $context Context parameter
     *
     * @return ComActorsControllerDefault
     */
    public function setView($view)
    {
        parent::setView($view);

        if (!$this->_view instanceof ComBaseViewAbstract) {
            $name = AnInflector::isPlural($this->view) ? 'media' : 'medium';
            $defaults[] = 'ComMediumView'.ucfirst($view).ucfirst($this->_view->name);
            $defaults[] = 'ComMediumView'.ucfirst($name).ucfirst($this->_view->name);
            register_default(array('identifier' => $this->_view, 'default' => $defaults));
        }

        return $this;
    }

    /**
     * Can be used as a cabllack to automatically create a story.
     *
     * @param AnCommandContext $context
     *
     * @return ComStoriesDomainEntityStory
     */
    public function createStoryCallback(AnCommandContext $context)
    {
        if ($context->result !== false) {
            $data = $context->data;
            $name = $this->getIdentifier()->name.'_'.$context->action;
            $context->append(array(
                'story' => array(
                    'component' => 'com_'.$this->getIdentifier()->package,
                    'name' => $name,
                    'owner' => $this->actor,
                    'object' => $this->getItem(),
                    'target' => $this->actor,
                    'comment' => $this->isCommentable() ? $data->comment : null,
                ),
            ));
            $story = $this->createStory(AnConfig::unbox($context->story));
            $data->story = $story;

            return $story;
        }

        return $context->result;
    }
}
