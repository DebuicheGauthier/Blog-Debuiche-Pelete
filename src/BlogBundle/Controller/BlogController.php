<?php

namespace BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BlogBundle\Entity\Article;
use BlogBundle\Entity\Comment;
use BlogBundle\Form\ArticleType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class BlogController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getEntityManager();
        $articles = $em->getRepository("BlogBundle:Article")->findAll();
        $user = $this->getUser();
        if($user != NULL)
        {
            $name = $user->getUsername();
        }
        else
        {
            $name = NULL;
        }
        return $this->render('BlogBundle:Blog:index.html.twig', array('articles' => $articles,'name' => $name));
    }
    
    public function profileAction(Request $request)
    {   
        $em = $this->getDoctrine()->getEntityManager();
        $user = $this->getUser();
        if($this->get('security.authorization_checker')->isGranted('ROLE_USER'))
        {
            $name = $user->getUsername();
            if($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            {
                $articles = $em->getRepository("BlogBundle:Article")->findAll();
            }  
            else
            {
                $articles = $em->getRepository("BlogBundle:Article")->findByauteur($name);
            }
        }
        else
        {
            $name = NULL;
            $articles = NULL;
        }
        return $this->render('BlogBundle:Blog:profile.html.twig', array('articles' => $articles, 'name' => $name));
    }
    
    public function newAction(Request $request)
    {
        $user = $this->getUser();

        $em = $this->getDoctrine()->getEntityManager();
        $article = new Article();
        if($user != NULL)
        {
            $name = $user->getUsername();
            $article->setAuteur($name);
            $article->setDate(new \DateTime());
            $article->setContenu('Ecrivez votre article ici');

            $form = $this->createFormBuilder($article)
                ->add('titre', 'Symfony\Component\Form\Extension\Core\Type\TextType')
                ->add('image', 'Symfony\Component\Form\Extension\Core\Type\UrlType')
                ->add('description', 'Symfony\Component\Form\Extension\Core\Type\TextType')
                ->add('contenu', 'Symfony\Component\Form\Extension\Core\Type\TextareaType')  
                ->add('save', 'Symfony\Component\Form\Extension\Core\Type\SubmitType', array('label' => 'Créer'))
                ->getForm();
        }
        else
        {
            $name = NULL;
            $article->setDate(new \DateTime());
            $article->setContenu('Ecrivez votre article ici');

            $form = $this->createForm($article)
                ->add('titre', 'Symfony\Component\Form\Extension\Core\Type\TextType')
                ->add('auteur', 'Symfony\Component\Form\Extension\Core\Type\TextType')
                ->add('image', 'Symfony\Component\Form\Extension\Core\Type\UrlType')
                ->add('description', 'Symfony\Component\Form\Extension\Core\Type\TextType')
                ->add('contenu', 'Symfony\Component\Form\Extension\Core\Type\TextareaType')  
                ->add('save', 'Symfony\Component\Form\Extension\Core\Type\SubmitType', array('label' => 'Créer'))
                ->getForm();
        }


        if($request->isMethod('POST')){
            $form->submit($request->request->get($form->getName()));

            $article = $form->getData();
            $em->persist($article);
            $em->flush();

            return $this->redirect($this->generateUrl("blog_homepage"));
        }

        return $this->render('BlogBundle:Blog:new.html.twig', array('form' => $form->createView(),'name' => $name
        ));
    }
    
    public function lectureAction(Request $request)
    {
        $id = $_GET['id'];
        $em = $this->getDoctrine()->getEntityManager();
        $article = $em->getRepository("BlogBundle:Article")->findOneById($id);
        
        $user = $this->getUser();
        if($user != NULL)
        {
            $name = $user->getUsername();
        }
        else
        {
            $name = NULL;
        }
        
        return $this->render('BlogBundle:Blog:lecture.html.twig', array('article' => $article,'name' => $name));
    }
    
    public function deleteAction()
    {
        $id = $_GET['id'];
        $user = $this->getUser();
        if($user != NULL)
        {
            $name = $user->getUsername();
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository("BlogBundle:Article")->findOneById($id);
            if($name == $entity->getAuteur() || $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'))
            {
                $em->remove($entity);
                $em->flush();
                return $this->redirect($this->generateUrl('blog_profile'));
            }
        }
        else
        {
            $name = NULL;
        }
        return $this->redirect($this->generateUrl('blog_profile'));
    }
}