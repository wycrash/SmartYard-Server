<?php

    /**
     * backends cs namespace
     */

    namespace backends\cs {

        use backends\backend;

        /**
         * base cs class
         */

        abstract class cs extends backend {
            /**
             * @param $sheet
             * @param $date
             * @return mixed
             */
            public function getCS($sheet, $date, $extended = false)
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                $css = $files->searchFiles([
                    "metadata.type" => "csheet",
                    "metadata.sheet" => $sheet,
                    "metadata.date" => $date,
                ]);

                $cs = "{\n\t\"sheet\": \"$sheet\",\n\t\"date\": \"$date\"\n}";

                foreach ($css as $s) {
                    $cs = $files->streamToContents($files->getFileStream($s["id"])) ? : $cs;
                    break;
                }

                if ($extended) {
                    return [
                        "sheet" => json_decode($cs),
                        "cells" => [ ],
                    ];
                } else {
                    return $cs;
                }
            }

            /**
             * @param $sheet
             * @param $date
             * @param $data
             * @return boolean
             */
            public function putCS($sheet, $date, $data)
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                $css = $files->searchFiles([
                    "metadata.type" => "csheet",
                    "metadata.sheet" => $sheet,
                    "metadata.date" => $date,
                ]);

                foreach ($css as $s) {
                    $cs = $files->deleteFile($s["id"]);
                }

                return $files->addFile($date . "_" . $sheet . ".json", $files->contentsToStream($data), [
                    "type" => "csheet",
                    "sheet" => $sheet,
                    "date" => $date,
                ]);
            }

            /**
             * @param $date
             * @return boolean
             */
            public function deleteCS($sheet, $date)
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                $css = $files->searchFiles([
                    "metadata.type" => "csheet",
                    "metadata.sheet" => $sheet,
                    "metadata.date" => $date,
                ]);

                foreach ($css as $s) {
                    $cs = $files->deleteFile($s["id"]);
                }

                return true;
            }

            /**
             * @return false|array
             */
            public function getCSes()
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                return $files->searchFiles([
                    "metadata.type" => "csheet",
                ]);
            }

            /**
             * @param $action
             * @param $sheet
             * @param $date
             * @param $col
             * @param $row
             * @param $uid
             */
            public function setCell($action, $sheet, $date, $col, $row, $uid)
            {
                switch ($action) {
                    case "claim":
                        break;

                    case "unClaim":
                        break;

                    case "reserve":
                        break;

                    case "free":
                        break;
                }

                return true;
            }

            /**
             * @param $sheet
             * @param $date
             * @param $col
             * @param $row
             */
            public function getCellByXYZ($sheet, $date, $col, $row)
            {

            }

            /**
             * @param $uid
             */
            public function getCellByUID($uid)
            {

            }

            /**
             * @param $sheet
             * @param $date
             */
            public function cells($sheet, $date)
            {

            }
            
        }
    }