<?php

    /**
     * backends tt namespace
     */

    namespace backends\tt {

        use api\accounts\password;

        /**
         * internal.db tt class
         */

        class internal extends tt {

            public function allow($params)
            {
                return true;
            }

            public function capabilities()
            {
                return [
                    "mode" => "rw",
                ];
            }

            public function cleanup()
            {
                return parent::cleanup(); // TODO: Change the autogenerated stub
            }

            /**
             * get projects
             *
             * @return false|array[]
             */
            public function getProjects()
            {
                try {
                    $projects = $this->db->query("select project_id, acronym, project from tt_projects order by acronym", \PDO::FETCH_ASSOC)->fetchAll();
                    $_projects = [];

                    foreach ($projects as $project) {
                        $_projects[] = [
                            "projectId" => $project["project_id"],
                            "acronym" => $project["acronym"],
                            "project" => $project["project"],
                        ];
                    }

                    return $_projects;
                } catch (\Exception $e) {
                    return false;
                }
            }

            /**
             * get project, if $project_id is false, returns all projects
             *
             * @param $projectId integer project_id
             * @return false|array
             */
            public function getProject($projectId)
            {
                if (!checkInt($projectId)) {
                    return false;
                }

                try {
                    $project = $this->db->query("select project_id, acronym, project from tt_projects where project_id = $projectId", \PDO::FETCH_ASSOC)->fetchAll();

                    if (count($project)) {
                        $workflows = $this->db->query("select workflow from tt_projects_workflows where project_id = $projectId", \PDO::FETCH_ASSOC)->fetchAll();

                        $w = [];
                        foreach ($workflows as $workflow) {
                            $w[] = $workflow["workflow"];
                        }

                        $resolutions = $this->db->query("select resolution_id from tt_projects_resolutions where project_id = $projectId", \PDO::FETCH_ASSOC)->fetchAll();

                        $r = [];
                        foreach ($resolutions as $resolution) {
                            $r[] = $resolution["resolution_id"];
                        }

                        return [
                            "projectId" => $project[0]["project_id"],
                            "acronym" => $project[0]["acronym"],
                            "project" => $project[0]["project"],
                            "workflows" => $w,
                            "resolutions" => $r,
                        ];

                    } else {
                        return false;
                    }
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }
            }

            /**
             * @param $acronym
             * @param $project
             *
             * @return false|integer
             */
            public function addProject($acronym, $project)
            {
                $acronym = trim($acronym);
                $project = trim($project);

                if (!$acronym || !$project) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("insert into tt_projects (acronym, project) values (:acronym, :project)");
                    if (!$sth->execute([
                        ":acronym" => $acronym,
                        ":project" => $project,
                    ])) {
                        return false;
                    }

                    return $this->db->lastInsertId();
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }
            }

            /**
             * @param $projectId integer
             * @param $acronym string
             * @param $project string
             * @return boolean
             */
            public function modifyProject($projectId, $acronym, $project)
            {
                if (!checkInt($projectId) || !trim($acronym) || !trim($project)) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("update tt_projects set acronym = :acronym, project = :project where project_id = $projectId");
                    $sth->execute([
                        ":acronym" => $acronym,
                        ":project" => $project,
                    ]);
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }

            /**
             * delete project and all it derivatives
             *
             * @param $projectId
             * @return boolean
             */
            public function deleteProject($projectId)
            {
                if (!checkInt($projectId)) {
                    return false;
                }

                try {
                    $this->db->exec("delete from tt_projects where project_id = $projectId");
                    // TODO: delete all derivatives
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }

            /**
             * get workflow aliases
             *
             * @return false|array
             */
            public function getWorkflowAliases()
            {
                try {
                    $workflows = $this->db->query("select workflow, alias from tt_workflows_aliases order by workflow", \PDO::FETCH_ASSOC)->fetchAll();
                    $_workflows = [];

                    foreach ($workflows as $workflow) {
                        $_workflows[] = [
                            "workflow" => $workflow["workflow"],
                            "alias" => $workflow["alias"],
                        ];
                    }

                    return $_workflows;
                } catch (\Exception $e) {
                    return false;
                }
            }

            /**
             * set workflow alias
             *
             * @param $workflow
             * @param $alias
             * @return boolean
             */
            public function setWorkflowAlias($workflow, $alias)
            {
                $alias = trim($alias);

                if (!$alias) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("insert into tt_workflows_aliases (workflow) values (:workflow)");
                    $sth->execute([
                        ":workflow" => $workflow,
                    ]);
                } catch (\Exception $e) {
                    // non uniq?
                }

                try {
                    $sth = $this->db->prepare("update tt_workflows_aliases set alias = :alias where workflow = :workflow");
                    $sth->execute([
                        ":workflow" => $workflow,
                        ":alias" => $alias,
                    ]);
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }

            /**
             * @param $projectId
             * @param $workflows
             * @return boolean
             */
            public function setProjectWorkflows($projectId, $workflows)
            {
                // TODO: add transaction, commint, rollback

                if (!checkInt($projectId)) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("insert into tt_projects_workflows (project_id, workflow) values (:project_id, :workflow)");
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                try {
                    $this->db->exec("delete from tt_projects_workflows where project_id = $projectId");
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                try {
                    foreach ($workflows as $workflow) {
                        if (!$sth->execute([
                            ":project_id" => $projectId,
                            ":workflow" => $workflow,
                        ])) {
                            return false;
                        }
                    }
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }

            /**
             * @return false|array
             */
            public function getStatuses()
            {
                try {
                    $statuses = $this->db->query("select status_id, status, status_display from tt_issue_statuses order by status", \PDO::FETCH_ASSOC)->fetchAll();
                    $_statuses = [];

                    foreach ($statuses as $statuse) {
                        $_statuses[] = [
                            "statusId" => $statuse["status_id"],
                            "status" => $statuse["status"],
                            "statusDisplay" => $statuse["status_display"],
                        ];
                    }

                    return $_statuses;
                } catch (\Exception $e) {
                    return false;
                }
            }

            /**
             * @param $statusId
             * @param $display
             * @return boolean
             */
            public function moodifyStatus($statusId, $display)
            {
                $display = trim($display);

                if (!checkInt($statusId) || !$display) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("update tt_issue_statuses set status_display = :status_display where status_id = $statusId");
                    $sth->execute([
                        ":status_display" => $display,
                    ]);
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }

            /**
             * @return false|array
             */
            public function getResolutions()
            {
                try {
                    $resolutions = $this->db->query("select resolution_id, resolution from tt_issue_resolutions order by resolution", \PDO::FETCH_ASSOC)->fetchAll();
                    $_resolutions = [];

                    foreach ($resolutions as $resolution) {
                        $_resolutions[] = [
                            "resolutionId" => $resolution["resolution_id"],
                            "resolution" => $resolution["resolution"],
                        ];
                    }

                    return $_resolutions;
                } catch (\Exception $e) {
                    return false;
                }
            }

            /**
             * @param $resolution
             * @return false|integer
             */
            public function addResolution($resolution)
            {
                $resolution = trim($resolution);

                if (!$resolution) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("insert into tt_issue_resolutions (resolution) values (:resolution)");
                    if (!$sth->execute([
                        ":resolution" => $resolution,
                    ])) {
                        return false;
                    }

                    return $this->db->lastInsertId();
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }
            }

            /**
             * @param $resolutionId
             * @param $resolution
             * @return boolean
             */
            public function modifyResolution($resolutionId, $resolution)
            {
                $resolution = trim($resolution);

                if (!checkInt($resolutionId) || !$resolution) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("update tt_issue_resolutions set resolution = :resolution where resolution_id = $resolutionId");
                    $sth->execute([
                        ":resolution" => $resolution,
                    ]);
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }

            /**
             * @param $resolutionId
             * @return boolean
             */
            public function deleteResolution($resolutionId)
            {
                if (!checkInt($resolutionId)) {
                    return false;
                }

                try {
                    $this->db->exec("delete from tt_issue_resolutions where resolution_id = $resolutionId");
                    $this->db->exec("delete from tt_projects_resolutions where resolution_id = $resolutionId");
                    // TODO: delete all derivatives
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }

            /**
             * @param $projectId
             * @param $resolutions
             * @return boolean
             */
            public function setProjectResolutions($projectId, $resolutions)
            {
                // TODO: add transaction, commint, rollback

                if (!checkInt($projectId)) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("insert into tt_projects_resolutions (project_id, resolution_id) values (:project_id, :resolution_id)");
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                try {
                    $this->db->exec("delete from tt_projects_resolutions where project_id = $projectId");
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                try {
                    foreach ($resolutions as $resolution) {
                        if (!checkInt($resolution)) {
                            return false;
                        }
                        if (!$sth->execute([
                            ":project_id" => $projectId,
                            ":resolution_id" => $resolution,
                        ])) {
                            return false;
                        }
                    }
                } catch (\Exception $e) {
                    error_log(print_r($e, true));
                    return false;
                }

                return true;
            }
        }
    }
