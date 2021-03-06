<?php

namespace Provider\Controller;

use Silex\ControllerProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;

use Form;

class Project implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**
         * Adds a comment to a project
         */
        $controllers->post('/{id}/comment', function($id) use ($app) {
            $form = $app['form.factory']->create(new Form\CommentType());
            $form->bindRequest($app['request']);

            if ($form->isValid()) {
                $comment = $form->getData();

                unset($comment['id']);

                $comment['project_id']   = $id;
                $comment['username']     = $app['session']->get('username');
                $comment['content_html'] = $app['markdown']($comment['content']);

                $app['comments']->insert($comment);
                $app['projects']->update(array('last_commented_at' => date('Y-m-d H:i:s')), array('id' => $id));

                return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
            }

            $project  = $app['projects']->find($id);
            $comments = $app['comments']->findByProjectId($id);

            return $app['twig']->render('Project/show.html.twig', array(
                'form'     => $form->createView(),
                'project'  => $project,
                'comments' => $comments,
            ));
         })->bind('project_comment');

         /**
          * Adds a link to a project
          */
        $controllers->post('/{id}/link', function($id) use ($app) {
            $form = $app['form.factory']->create(new Form\ProjectLinkType());
            $form->bindRequest($app['request']);

            if ($form->isValid()) {
                $projectLink = $form->getData();

                unset($projectLink['id']);

                $projectLink['project_id'] = $id;

                $app['project_links']->insert($projectLink);

                return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
            }

            $project     = $app['projects']->find($id);
            $comments    = $app['comments']->findByProjectId($id, 0);
            $commentForm = $app['form.factory']->create(new Form\CommentType());
            $voters      = $app['project_votes']->findByProjectId($id);
            $links       = $app['project_links']->findByProjectId($id);

            return $app['twig']->render('Project/show.html.twig', array(
                'link_form'        => $form->createView(),
                'comment_form'     => $commentForm->createView(),
                'project'          => $project,
                'comments'         => $comments,
                'skipped_comments' => 0,
                'voters'           => $voters,
                'links'            => $links,
                'show_link_form'   => true,
            ));
        })->bind('project_link');

        /**
         * Deletes a project
         */
        $controllers->post('/{id}/delete', function($id) use ($app) {
           $app['projects']->delete(array('id' => $id));
           return $app->redirect($app['url_generator']->generate('homepage'));
        })->bind('project_delete');

        /**
         * Shows the edit form for a project
         */
        $controllers->get('/{id}/edit', function($id) use ($app) {
            $project = $app['projects']->find($id);
            $form    = $app['form.factory']->create(new Form\ProjectType(), $project, array(
                'categories' => $app['project.categories']
            ));

            return $app['twig']->render('Project/edit.html.twig', array(
                'form'    => $form->createView(),
                'project' => $project
            ));

        })->bind('project_edit');

        /**
         * Actually updates a project
         */
        $controllers->post('/{id}', function($id) use ($app) {
            $project = $app['projects']->find($id);
            $form    = $app['form.factory']->create(new Form\ProjectType(), $project, array(
                'categories' => $app['project.categories']
            ));

            $form->bindRequest($app['request']);

            if ($form->isValid()) {
                $project = $form->getData();

                $project['id'] = $id;
                $project['description_html'] = $app['markdown']($project['description']);

                $app['projects']->update($project, array('id' => $id));

                return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
            }

            return $app['twig']->render('Project/edit.html.twig', array(
                'form'    => $form->createView(),
                'project' => $project,
            ));
        })->bind('project_update');

        /**
         * Project creation form
         */
        $controllers->get('/new', function() use ($app) {
            $form = $app['form.factory']->create(new Form\ProjectType(), array(), array(
                'categories' => $app['project.categories']
            ));

            return $app['twig']->render('Project/new.html.twig', array(
                'form' => $form->createView(),
            ));
        })->bind('project_new');

        /**
         * Project show
         */
        $controllers->get('/{id}/{allComments}', function($id, $allComments = false) use ($app) {
            $project    = $app['projects']->findWithHasVoted($id, $app['session']->get('username'));
            $comments   = $app['comments']->findByProjectId($id, $allComments ? 0 : 5);
            $nbComments = $app['comments']->countByProjectId($id);
            $voters     = $app['project_votes']->findByProjectId($id);
            $links      = $app['project_links']->findByProjectId($id);

            $commentForm = $app['form.factory']->create(new Form\CommentType());
            $linkForm    = $app['form.factory']->create(new Form\ProjectLinkType());

            return $app['twig']->render('Project/show.html.twig', array(
                'comment_form'     => $commentForm->createView(),
                'link_form'        => $linkForm->createView(),
                'project'          => $project,
                'comments'         => $comments,
                'skipped_comments' => $nbComments - count($comments),
                'voters'           => $voters,
                'links'            => $links,
            ));
        })->bind('project_show')->value('allComments', 0)->assert('allComments', '\d+');

        /**
         * Project creation
         */
        $controllers->post('', function() use ($app) {
            $form = $app['form.factory']->create(new Form\ProjectType(), array(), array(
                'categories' => $app['project.categories']
            ));

            $form->bindRequest($app['request']);

            if ($form->isValid()) {

                $project = $form->getData();

                unset($project['id']);

                $project['username']         = $app['session']->get('username');
                $project['description_html'] = $app['markdown']($project['description']);

                $app['projects']->insert($project);

                return $app->redirect('/');
            }

            return $app['twig']->render('Project/new.html.twig', array(
                'form' => $form->createView(),
            ));

        })->bind('project_create');

        /**
         * Deletes a comment
         */
        $controllers->post('/comment/{id}/delete', function($id) use ($app) {
            $comment = $app['comments']->find($id);
            $app['comments']->delete(array('id' => $id));

            return $app->redirect($app['url_generator']->generate('project_show', array('id' => $comment['project_id'])));
        })->bind('comment_delete');

        /**
         * Vote for project
         */
        $controllers->get('/{id}/vote', function($id) use ($app) {
            $username = $app['session']->get('username');
            
            if (!$app['project_votes']->existsForProjectAndUser($id, $username)) {
                $app['project_votes']->insert(array(
                    'username'   => $username,
                    'project_id' => $id,
                ));
            }

            return $app->redirect(urldecode($app['request']->query->get('return_url', '/')));
        })->bind('project_vote');

        /**
        * Unvote project
        */
        $controllers->get('/{id}/unvote', function($id) use ($app) {
            $app['project_votes']->delete(array(
                'project_id' => $id,
                'username'   => $app['session']->get('username'),
            ));

            return $app->redirect(urldecode($app['request']->query->get('return_url', '/')));
        })->bind('project_unvote');

        return $controllers;
    }
}