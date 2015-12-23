<?php

namespace Projet\DownloadBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Transmission\Transmission;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use \Symfony\Component\HttpFoundation\BinaryFileResponse;
use Projet\DownloadBundle\Form\Torrent;
use Projet\DownloadBundle\Form\TorrentType;
use Projet\DownloadBundle\Form\Folder;
use Projet\DownloadBundle\Form\MkFolderType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PublicController extends Controller {

    function indexAction() {

        // Parsing de la commande Top pour récupérer les valeurs de différentes composantes du PC.
        $test = null;
        $tab = null;
        $ram = 0;
        $cpux = 0;
        exec('top -b -n 1 -o %CPU', $tests);
        foreach ($tests as $test) {
            if (strstr($test, "top")) {
                
            } elseif (strstr($test, "Tasks")) {
                
            } elseif (strstr($test, "%Cpu(s):")) {
                list($unuse, $use) = explode(":", $test);
                $cpu = explode(",", $use);
            } elseif (strstr($test, "KiB Mem:")) {
                list($unuse, $use) = explode(":", $test);
                $mem = explode(",", $use);
                $mem[0] = (int) ($mem[0] / 1024);
                //$mem[0] = $this->formatSizeUnits($mem[0]);
                //$mem[0] = floatval($mem[0]);
                $mem[1] = (int) ($mem[1] / 1024);
                //$mem[1] = $this->formatSizeUnits($mem[1]*1024);
                //$mem[1] = floatval($mem[1]);
                $mem[2] = (int) ($mem[2] / 1024);
                //$mem[2] = $this->formatSizeUnits($mem[2]);
                //$mem[2] = floatval($mem[2]);
            } elseif (strstr($test, "KiB Swap:")) {
                list($unuse, $use) = explode(":", $test);
                $swap = explode(",", $use);
                $swap[0] = (int) ($swap[0] / 1024);
                //$swap[0] = $this->formatSizeUnits($swap[0]);
                //$swap[0] = floatval($swap[0]);
                $swap[1] = (int) ($swap[1] / 1024);
                //$swap[1] = $this->formatSizeUnits($swap[1]);
                //$swap[1] = floatval($swap[1]);
                $swap[2] = (int) ($swap[2] / 1024);
                //$swap[2] = $this->formatSizeUnits($swap[2]);
                //$swap[2] = floatval($swap[2]);
            } elseif (strstr($test, "PID")) {
                
            } else {
                $process = preg_split('/\s+/', $test);
                if (sizeof($process) == 1) {
                    
                } elseif (sizeof($process) == 13) {
                    $tab[] = array("PID" => $process[1], "USER" => $process[2], "RES" => $process[6],
                        "%CPU" => $process[9], "%MEM" => $process[10], "TIME+" => $process[11], "COMMAND" => $process[12],);
                    $cpux+=$process[9];
                } elseif (sizeof($process) == 12) {
                    $tab[] = array("PID" => $process[0], "USER" => $process[1], "RES" => $process[5],
                        "%CPU" => $process[8], "%MEM" => $process[9], "TIME+" => $process[10], "COMMAND" => $process[11],);
                    $cpux+=$process[8];
                }
            }
        }
        $cpux = $cpux / 4;
        // espace utilisé sur le disque dur.
        $du = (disk_total_space("/")) - (disk_free_space("/"));

        $ds = $this->formatSizeUnits(disk_total_space("/"));
        list($pre, $post) = explode(",", $ds);
        $ds = floatval($pre . $post);

        $df = $this->formatSizeUnits(disk_free_space("/"));
        if (strstr($df, ",")) {
            list($pre, $post) = explode(",", $df);
            $df = floatval($pre . $post);
        } else {
            $df = floatval($df);
        }

        $dub = $this->formatSizeUnits($du);
        if (strstr($dub, ",")) {
            list($pre, $post) = explode(",", $dub);
            $dub = floatval($pre . $post);
        } else {
            $dub = floatval($dub);
        }


        return $this->render("DownloadBundle:Public:index.html.twig", array('tab' => $tests, 'mTotal' => $mem[0], 'mUsed' => $mem[1], 'mFree' => $mem[2],
                    'sTotal' => $swap[0], 'sUsed' => $swap[1], 'sFree' => $swap[2],
                    'ds' => $ds, 'df' => $df,
                    'du' => $dub, 'tabx' => $tab, 'cpu' => $cpux));
    }

    function formatSizeUnits($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    function listing($dir) {
        //$dir=  urldecode($dir);
        $file = null;
        $tab = null;
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {

                while (($file = readdir($dh)) !== false) {
                    if ($file != "." && $file != ".." && $file != ".htpasswd" && $file != ".htaccess" && $file != "robot.txt") {
                        if (is_file($dir . "/" . $file)) {
                            $tab[] = array("name" => $file, "size" => $this->formatSizeUnits(filesize($dir . "/" . $file)), "modif" => date("F d Y H:i:s.", filemtime($dir . "/" . $file)));
                        } else
                            $tab[] = array("name" => $file, "size" => "", "modif" => date("F d Y H:i:s.", filemtime($dir . "/" . $file)));
                    }
                }
            }

            closedir($dh);
        }
        if (!is_null($tab)) {
            asort($tab);
        }
        return $tab;
    }

    function downloadFiles($folder, $file) {
        $response = new BinaryFileResponse($folder . "/" . $file);

        $d = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file, mb_convert_encoding($file, "ASCII", "auto")
        );

        $response->headers->set('Content-Disposition', $d);

        return $response;
    }

    function dellFiles($file, $dir) {

        if ($dir != "null") {
            if (is_dir("/var/www/html/projet_download/transmission-download/" . $dir . "/" . $file)) {
                $objects = scandir("/var/www/html/projet_download/transmission-download/" . $dir . "/" . $file);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (filetype("/var/www/html/projet_download/transmission-download/" . $dir . "/" . $file . "/" . $object) == "dir")
                            rmdir("/var/www/html/projet_download/transmission-download/" . $dir . "/" . $file . "/" . $object);
                        else
                            unlink("/var/www/html/projet_download/transmission-download/" . $dir . "/" . $file . "/" . $object);
                    }
                }
                reset($objects);
                rmdir("/var/www/html/projet_download/transmission-download/" . $dir . "/" . $file);
            }
            else {
                unlink("/var/www/html/projet_download/transmission-download/" . $dir . "/" . $file);
            }
        } elseif ($dir == "null") {
            if (is_dir("/var/www/html/projet_download/transmission-download/" . $file)) {
                $objects = scandir("/var/www/html/projet_download/transmission-download/" . $file);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (filetype("/var/www/html/projet_download/transmission-download/" . $file . "/" . $object) == "dir")
                            rmdir("/var/www/html/projet_download/transmission-download/" . $file . "/" . $object);
                        else
                            unlink("/var/www/html/projet_download/transmission-download/" . $file . "/" . $object);
                    }
                }
                reset($objects);
                rmdir("/var/www/html/projet_download/transmission-download/" . $file);
            }
            else {
                unlink("/var/www/html/projet_download/transmission-download/" . $file);
            }
        }
    }

    function dossierAction($name, $name1, $name2, $name3, $name4) {
        $folder = "dossier";
        $back = null;
        $td = "transmission-download/";
        $tab = $this->listing("/var/www/html/projet_download/transmission-download/");
        if (!is_null($name) && $name != "suppr" && $name != "move" && $name != "mkdir") {
            if (is_file("/var/www/html/projet_download/transmission-download/" . $name)) {
                return $this->downloadFiles("/var/www/html/projet_download/transmission-download/", $name);
            } else {
                $tab = $this->listing("/var/www/html/projet_download/transmission-download/" . $name);
                $folder = $name;
                $back = "dossier";
            }
            if (!is_null($name1) && $name1 != "suppr" && $name1 != "move" && $name1 != "mkdir") {
                if (is_file("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1)) {
                    return $this->downloadFiles("/var/www/html/projet_download/transmission-download/" . $name, $name1);
                } else {
                    $tab = $this->listing("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1);
                    $folder = $name1;
                    $back = "dossier/" . $name;
                }
                if (!is_null($name2) && $name2 != "suppr" && $name2 != "move" && $name2 != "mkdir") {
                    if (is_file("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2)) {
                        return $this->downloadFiles("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/", $name2);
                    } else {
                        $tab = $this->listing("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2);
                        $folder = $name2;
                        $back = "dossier/" . $name . "/" . $name1;
                    }
                    if (!is_null($name3) && $name3 != "suppr" && $name3 != "move" && $name3 != "mkdir") {
                        if (is_file("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2 . "/" . $name3)) {
                            return $this->downloadFiles("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2 . "/", $name3);
                        } else {
                            $tab = $this->listing("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2 . "/" . $name3);
                            $folder = $name3;
                            $back = "dossier/" . $name . "/" . $name1 . "/" . $name2;
                        }
                        if (!is_null($name4) && $name4 != "suppr" && $name4 != "move" && $name4 != "mkdir") {
                            if (is_file("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2 . "/" . $name3 . "/" . $name4)) {
                                return $this->downloadFiles("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2 . "/" . $name3 . "/", $name4);
                            } else {
                                $tab = $this->listing("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name1 . "/" . $name2 . "/" . $name3 . "/" . $name4);
                                $folder = $name4;
                                $back = "dossier/" . $name . "/" . $name1 . "/" . $name2 . "/" . $name3;
                            }
                        }
                    }
                }
            }
        }
        $tabx = $this->listing("/var/www/html/projet_download/transmission-download/Series");
        if ($name == "suppr" || $name1 == "suppr" || $name2 == "suppr" || $name3 == "suppr" || $name4 == "suppr") {
            if ($name == "suppr") {
                $this->dellFiles($name1, "null");
                return $this->redirect($this->generateUrl('Download_dossier'));
            } elseif ($name1 == "suppr") {
                $this->dellFiles($name2, $name);
                return $this->redirect($this->generateUrl('Download_dossier') . "/" . $name);
            } elseif ($name2 == "suppr") {
                $this->dellFiles($name3, $name . "/" . $name1);
                return $this->redirect($this->generateUrl('Download_dossier') . "/" . $name . "/" . $name1);
            } elseif ($name3 == "suppr") {
                $this->dellFiles($name4, $name . "/" . $name1 . "/" . $name2);
                return $this->redirect($this->generateUrl('Download_dossier') . "/" . $name . "/" . $name1 . "/" . $name2);
            }
            return $this->redirect($this->generateUrl('Download_dossier'));
        }
        if ($name == "move" || $name1 == "move" || $name2 == "move" || $name3 == "move" || $name4 == "move") {
            if ($name == "move") {
                rename("/var/www/html/projet_download/transmission-download/" . $name1, "/var/www/html/projet_download/transmission-download/" . $name2 . "/" . $name1);
                return $this->redirect($this->generateUrl('Download_dossier'));
            } elseif ($name1 == "move") {
                rename("/var/www/html/projet_download/transmission-download/" . $name . "/" . $name2, "/var/www/html/projet_download/transmission-download/" . $name . "/" . $name3 . "/" . $name2);
                return $this->redirect($this->generateUrl('Download_dossier') . "/" . $name);
            }
            return $this->redirect($this->generateUrl('Download_dossier'));
        }


        $folderNameEnquiry = new Folder();
        $folderNameForm = $this->createForm(new MkFolderType(), $folderNameEnquiry);
        $request = $this->getRequest();
        $nameFolder = null;
        if ($request->getMethod() == 'POST') {
            $folderNameForm->bind($request);
            if ($folderNameEnquiry->getDossier() == "films") {
                $nameFolder = "Films";
            } elseif ($folderNameEnquiry->getDossier() == "series") {
                $nameFolder = "Series";
            } elseif ($folderNameEnquiry->getDossier() == "books") {
                $nameFolder = "Books";
            } elseif ($folderNameEnquiry->getDossier() == "logiciels") {
                $nameFolder = "Logiciels";
            } elseif ($folderNameEnquiry->getDossier() == "jeux") {
                $nameFolder = "Jeux";
            } elseif ($folderNameEnquiry->getDossier() == "musiques") {
                $nameFolder = "Musiques";
            }
            if (is_null($folderNameEnquiry->getName())) {
                return $this->redirect($this->generateUrl('Download_dossier'));
            } else {
                mkdir("/var/www/html/projet_download/transmission-download/" . $nameFolder . "/" . $folderNameEnquiry->getName(), 0777);
                return $this->redirect($this->generateUrl('Download_dossier') . "/" . $nameFolder);
            }
        }

        $user = $this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN');
        return $this->render("DownloadBundle:Public:dossier.html.twig", array('tab' => $tab, 'folder' => $folder, 'back' => $back, 'tabx' => $tabx, 'form' => $folderNameForm->createView(), 'user' => $user));
    }

    function transmissionAction($name) {
        $downloadT = array();
        $transmission = $this->container->get('Transmission');
        $torrents = $transmission->all();
        $folder = "transmission/";
        $flag = 0;

        foreach ($torrents as $torrent) {

            if ($name == "paused") {
                $flag = 1;
                if ($torrent->isStopped()) {
                    $downloadT[] = array(
                        "name" => $torrent->getName(),
                        "percent" => $torrent->getPercentDone(),
                        "eta" => gmdate("H:i:s", $torrent->getEta()),
                        "size" => null,
                        "DorU" => $pureSeed = 0,
                        "torrentID" => $torrent->getId(),
                        "paused" => 1,
                    );
                }
            } elseif ($name == "down") {
                $flag = 1;
                if ($torrent->isDownloading()) {
                    $downloadT[] = array(
                        "name" => $torrent->getName(),
                        "percent" => $torrent->getPercentDone(),
                        "eta" => gmdate("H:i:s", $torrent->getEta()),
                        "size" => $this->formatSizeUnits($torrent->getSize()),
                        "DorU" => $pureSeed = 0,
                        "torrentID" => $torrent->getId(),
                        "paused" => 0,
                    );
                }
            } elseif ($name == "up") {
                $flag = 1;
                if ($torrent->isSeeding() && $torrent->isFinished()) {
                    $downloadT[] = array(
                        "name" => $torrent->getName(),
                        "percent" => $torrent->getPercentDone(),
                        "eta" => gmdate("H:i:s", $torrent->getEta()),
                        "size" => null,
                        "DorU" => $pureSeed = 1,
                        "torrentID" => $torrent->getId(),
                        "paused" => 0,
                    );
                }
            }

            if ($flag == 0) {
                if ($torrent->isDownloading()) {
                    $downloadT[] = array(
                        "name" => $torrent->getName(),
                        "percent" => $torrent->getPercentDone(),
                        "eta" => gmdate("H:i:s", $torrent->getEta()),
                        "size" => $this->formatSizeUnits($torrent->getSize()),
                        "DorU" => $pureSeed = 0,
                        "torrentID" => $torrent->getId(),
                        "paused" => 0,
                    );
                }
                if ($torrent->isSeeding() && $torrent->isFinished()) {
                    $downloadT[] = array(
                        "name" => $torrent->getName(),
                        "percent" => $torrent->getPercentDone(),
                        "eta" => gmdate("H:i:s", $torrent->getEta()),
                        "size" => null,
                        "DorU" => $pureSeed = 1,
                        "torrentID" => $torrent->getId(),
                        "paused" => 0,
                    );
                }
            }
        }

        if (preg_match("(^pau-([0-9]+))", $name, $tab)) {
            $torrentID = $transmission->get((int) $tab[1]);
            $transmission->stop($torrentID);
            return $this->redirect($this->generateUrl('Download_transmission'));
        }

        if (preg_match("(^del-([0-9]+))", $name, $tab)) {
          $torrentID = $transmission->get((int) $tab[1]);
          $transmission->remove($torrentID);
          return $this->redirect($this->generateUrl('Download_transmission'));
          } 

        if (preg_match("(^start-([0-9]+))", $name, $tab)) {
            $torrentID = $transmission->get((int) $tab[1]);
            $transmission->start($torrentID, true);
            return $this->redirect($this->generateUrl('Download_transmission'));
        }

        if (preg_match("(^mpeers-([0-9]+))", $name, $tab)) {
            $torrentID = $transmission->get((int) $tab[1]);
            $transmission->reannounce($torrentID);
            return $this->redirect($this->generateUrl('Download_transmission'));
        }


        $torrentEnquiry = new Torrent();
        $torrentForm = $this->createForm(new TorrentType(), $torrentEnquiry);
        $request = $this->getRequest();

        if ($request->getMethod() == 'POST') {
            $torrentForm->bind($request);

            if (is_null($torrentEnquiry->getMagnet()) && is_null($torrentEnquiry->getTorrent())) {
                return $this->redirect($this->generateUrl('Download_transmission'));
            }

            if (!is_null($torrentEnquiry->getMagnet()) && is_null($torrentEnquiry->getTorrent())) {
                $transmission->add($torrentEnquiry->getMagnet());
                return $this->redirect($this->generateUrl('Download_transmission'));
            }

            if (is_null($torrentEnquiry->getMagnet()) && !is_null($torrentEnquiry->getTorrent())) {
                if ($torrentEnquiry->getTorrent()->getExtension() != "torrent" || $torrentEnquiry->getTorrent()->getMimeType() != "application/x-bittorrent" ) {
                    $torrent = $torrentEnquiry->getTorrent()->move("/var/www/html/projet_download/web/torrent", $torrentEnquiry->getTorrent()->getClientOriginalName());
                    unlink((string) $torrent);
                    echo '<script type="text/javascript">window.alert("Ce fichier a été supprimé car non valide");</script>';
                    //return $this->redirect($this->generateUrl('Download_transmission'));
                } else {
                    $torrent = $torrentEnquiry->getTorrent()->move("/var/www/html/projet_download/web/torrent", $torrentEnquiry->getTorrent()->getClientOriginalName());
                    $transmission->add((string) $torrent);
                    return $this->redirect($this->generateUrl('Download_transmission'));
                }
            }
        }
        $user = $this->container->get('security.authorization_checker')->isGranted('ROLE_ADMIN');
        return $this->render("DownloadBundle:Public:transmission.html.twig", array('torrents' => $downloadT, 'transmissionFolder' => $folder, 'form' => $torrentForm->createView(),
                    'user' => $user));
    }

    function utilisateurAction() {
        return $this->render("DownloadBundle:Public:utilisateur.html.twig");
    }

    function optionsAction() {
        return $this->render("DownloadBundle:Public:options.html.twig");
    }

    function videoAction() {
        
        $video = "/dossier/Arrow.S04E07.720p.HDTV.X264-DIMENSION[brassetv]/Arrow.S04E07.720p.HDTV.X264-DIMENSION.mkv";
        return $this->render("DownloadBundle:Public:video.html.twig", array('video' => $video));
    }

}
