(function(angular, $, _) {
  var moduleName = "sqlTaskConfigurator";

  var moduleDependencies = ["ngRoute", "ui.sortable"];

  angular.module(moduleName, moduleDependencies);

  angular.module(moduleName).config([
    "$routeProvider",
    function($routeProvider) {
      $routeProvider.when("/sqltasks/configure/:tid", {
        controller: "sqlTaskConfiguratorCtrl",
        templateUrl: "~/sqlTaskConfigurator/sqlTaskConfigurator.html",
        resolve: {
          taskId: function($route) {
            return $route.current.params.tid;
          }
        }
      });
    }
  ]);

  angular.module(moduleName).service('loaderService', function() {
    //loader for execution block
    this.executionBlock = {
      'isLoaded' : false,
      'currentLoadedComponents' : 0,
      'componentsNumber' : 2,
    };
    this.updateExecutionBlock = function() {
      this.executionBlock.currentLoadedComponents = this.executionBlock.currentLoadedComponents  + 1;
      if (this.executionBlock.currentLoadedComponents >= this.executionBlock.componentsNumber) {
        this.executionBlock.isLoaded = true;
      }
    };
    this.isExecutionBlockLoaded = function() {
      return this.executionBlock.isLoaded;
    };

    //loader for elements
    this.loadedElements = [];
    this.isDataLoaded = function(elementId) {
      return this.loadedElements.includes(elementId);
    };
    this.setDataLoaded = function(elementId) {
      this.loadedElements.push(elementId);
    };

    this.resetData = function() {
      this.executionBlock.isLoaded = false;
      this.executionBlock.currentLoadedComponents = 0;
      this.executionBlock.componentsNumber = 2;
      this.loadedElements = [];
    };
  });

  angular.module(moduleName).controller("sqlTaskConfiguratorCtrl", function($scope, $location, taskId, loaderService) {
      $scope.isLoaded = false;
      if (!$scope.isLoaded) {
        loaderService.resetData();
        $scope.isLoaded = true;
      }
      $scope.executionSchedule = [];
      $scope.executionScheduleMessages = [];
      $scope.executionScheduleLimitMin = 3;
      $scope.executionScheduleLimit = $scope.executionScheduleLimitMin;
      $scope.ts = CRM.ts();
      $scope.taskOptions = {
        scheduled: ""
      };
      $scope.config = {
        actions: [],
        scheduled_month: "1",
        scheduled_weekday: "1",
        scheduled_day: "1",
        scheduled_hour: "0",
        scheduled_minute: "0",
        version: 2
      };
      $scope.isExecutionBlockLoaded = function () {
        return loaderService.isExecutionBlockLoaded();
      };
      $scope.taskId = taskId;

      $scope.onInfoPress = onInfoPress;
      $scope.getBooleanFromNumber = getBooleanFromNumber;

      $scope.$on("$viewContentLoaded", function() {
        setTimeout(function() {
          openCheckedActions();
        }, 1500);

        var form = document.querySelector("#sql-task-form");
        var triggerButton = document.querySelector(
          "#_qf_Configure_submit-bottom"
        );

        triggerButton.onclick = function() {
          openCheckedActions();
          setTimeout(function() {
            if (form.reportValidity()) {
              var preparedData = {};
              if (taskId) {
                Object.assign(preparedData, { id: taskId });
              }
              Object.assign(preparedData, $scope.taskOptions);

              preparedData.config = $scope.config;

              if (Array.isArray($scope.taskOptions.run_permissions)) {
                preparedData.run_permissions = $scope.taskOptions.run_permissions.join(",");
              }

              function submitCallback (result) {
                if (result.is_error && result.error_type === "CONCURRENT_CHANGES") {
                  CRM.confirm({
                    title: ts("Warning"),
                    message: ts(`${result.error_message}. Would you like to update the task anyway?`),
                    options: { yes: "Save anyway", no: "Reload page" },
                  }).on("crmConfirm:yes", () => {
                    preparedData.last_modified = null;
                    CRM.api3("Sqltask", "create", preparedData).done(submitCallback);
                  }).on("crmConfirm:no", () => {
                    window.location.reload();
                  });

                  return;
                }

                if (result.is_error) {
                  var errorMessage = ts('Error while ' + (Number(taskId) ? 'updating' : 'creating') + ' task');
                  CRM.alert(errorMessage, ts('Error'), 'error');
                  return;
                }

                var title = ts('Task ' + (Number(taskId) ? 'updated' : 'created'));
                var successMessage = ts('Task successfully ' + (Number(taskId) ? 'updated' : 'created'));
                CRM.alert(successMessage, title, 'success');
                $location.path("/sqltasks/manage/" + result.values.id);
                $scope.$apply();
              }

              CRM.api3("Sqltask", "create", preparedData).done(submitCallback);
            }
          }, 500);
        };
      });

      if (taskId) {
        CRM.api3("Sqltask", "get", {
          sequential: 1,
          id: taskId
        }).done(function(result) {
          if (!result.is_error) {
            var task = Object.assign({}, result.values);
            $scope.config = Object.assign({}, task.config);
            delete task["config"];
            $scope.taskOptions = task;

            if ($scope.taskOptions.run_permissions === '') {
              $scope.taskOptions.run_permissions = [];
            } else {
              $scope.taskOptions.run_permissions = $scope.taskOptions.run_permissions.split(",");
            }
            $scope.$apply();
            $scope.updateExecutionSchedule();
          }
        });
      }

      // Use configuration template if task is new
      if (taskId === "0") {
        loadConfigTemplate();
      }

      CRM.$(function($) {
        setTimeout(function() {
          $("body").on("click", ".input-checkbox", function(e) {
            e.stopPropagation();
          });
        }, 1500);
      });

      CRM.api3("Sqltaskfield", "getrunpermissions").done(function(result) {
        permissionsData = [];
        Object.keys(result.values[0]).map(key => {
          permissionsData.push({
            value: key,
            name: result.values[0][key]
          });
        });
        $scope.permissionsData = permissionsData;
        loaderService.updateExecutionBlock();
      });

      $scope.onSchedulingOptionChange = function(params) {
        Object.keys($scope.config).forEach(element => {
          switch (element) {
            case "scheduled_month":
              $scope.config[element] = "1";
              break;
            case "scheduled_weekday":
              $scope.config[element] = "1";
              break;
            case "scheduled_day":
              $scope.config[element] = "1";
              break;
            case "scheduled_hour":
              $scope.config[element] = "0";
              break;
            case "scheduled_minute":
              $scope.config[element] = "0";
              break;
            default:
              break;
          }
        });

        $scope.updateExecutionSchedule();
      };

      $scope.updateExecutionSchedule = function() {
        if ($scope.taskOptions.scheduled === '') {
          return;
        }

        $scope.showExecutionBlockPreloader();

        CRM.api3('sqltask', 'get_planning_schedule_dates', {
          "sequential": 1,
          "schedule_frequency": $scope.taskOptions.scheduled,
          "schedule_month": parseInt($scope.config.scheduled_month),
          "schedule_weekday": parseInt($scope.config.scheduled_weekday),
          "schedule_day": parseInt($scope.config.scheduled_day),
          "schedule_hour": parseInt($scope.config.scheduled_hour),
          "schedule_minute": parseInt($scope.config.scheduled_minute),
          "schedule_start_date": $scope.taskOptions.schedule_start_date,
          "iteration_count": parseInt($scope.executionScheduleLimit),
          "last_execution": $scope.taskOptions.last_execution,
        }).then(function(result) {
          if (result.is_error === 1) {
            console.error('sqltask.get_planning_schedule_dates error:');
            console.error(result.error_message);
            $scope.cleanScheduleMessage();
            $scope.showScheduleMessage(result.error_message, 'error');
          } else {
            $scope.cleanScheduleMessage();
            $scope.executionSchedule = result.values.schedule;
            $scope.executionScheduleMessages = result.values.messages;
            $scope.$apply();
          }
          $scope.hideExecutionBlockPreloader();
        }, function(error) {
          $scope.hideExecutionBlockPreloader();
        });
      };

      $scope.showExecutionBlockPreloader = function () {
        $('.sql-tasks-execution-wrap').addClass('loading');
      };

      $scope.hideExecutionBlockPreloader = function () {
        $('.sql-tasks-execution-wrap').removeClass('loading');
      };

      $scope.showScheduleMessage = function (message, type) {
        $scope.executionScheduleMessages.push({
          'type' : type,
          'content' : message,
        });
      };

      $scope.cleanScheduleMessage = function () {
        $scope.executionScheduleMessages = [];
      };

      $scope.increaseExecutionScheduleLimit = function () {
        $scope.executionScheduleLimit++;
        $scope.updateExecutionSchedule();
      };

      $scope.decreaseExecutionScheduleLimit = function () {
        $scope.executionScheduleLimit--;
        if ($scope.executionScheduleLimit <= $scope.executionScheduleLimitMin) {
          $scope.executionScheduleLimit = $scope.executionScheduleLimitMin;
        }
        $scope.updateExecutionSchedule();
      };

      var previousOrder = [];
      $scope.sortableOptions = {
        placeholder: 'sql-task-target-highlight-place',
        revert: 300,
        cursor: "move",
        scroll: true,
        update: function(e, ui) {
          previousOrder = $scope.config.actions.slice();
          $scope.$apply();
        },
        stop: function(e, ui) {
          var currentActionIndex = ui.item.sortable.dropindex;
          var currentAction = $scope.config.actions[currentActionIndex];
          var topAction = $scope.config.actions[currentActionIndex - 1];
          var bottomAction = $scope.config.actions[currentActionIndex + 1];
          var showError = function (extraMessage) {
            CRM.alert("Cannot move action to this position. " + extraMessage, "Invalid action order", "error");
            $scope.config.actions = previousOrder.slice();
            $scope.$apply();
          };

          if (!currentAction) {
            return;
          }

          switch (currentAction.type) {
            case "CRM_Sqltasks_Action_ErrorHandler":
              if ((bottomAction && bottomAction.type !== "CRM_Sqltasks_Action_ErrorHandler")
                || (topAction && topAction.type !== "CRM_Sqltasks_Action_PostSQL" &&
                topAction.type !== "CRM_Sqltasks_Action_SuccessHandler"
                && topAction.type !== "CRM_Sqltasks_Action_ErrorHandler")
              ) {
                showError('The "' + $scope.formNameFromType(currentAction.type) + '" action cannot occur before other types of actions.');
              }
              break;
            case "CRM_Sqltasks_Action_SuccessHandler":
              if (bottomAction && bottomAction.type !== "CRM_Sqltasks_Action_ErrorHandler" && bottomAction.type !== "CRM_Sqltasks_Action_SuccessHandler") {
                showError('The "' + $scope.formNameFromType(currentAction.type) + '" action cannot occur after this action.');
              } else if (topAction && topAction.type === "CRM_Sqltasks_Action_PostSQL" || topAction.type === "CRM_Sqltasks_Action_ErrorHandler") {
                showError('The "' + $scope.formNameFromType(currentAction.type)
                  + '" action cannot occur after the "' + $scope.formNameFromType("CRM_Sqltasks_Action_ErrorHandler") + '" action.');
              }
              break;
            case "CRM_Sqltasks_Action_PostSQL":
              if (bottomAction && bottomAction.type !== "CRM_Sqltasks_Action_ErrorHandler" &&
                bottomAction.type !== "CRM_Sqltasks_Action_SuccessHandler"
                && bottomAction.type !== "CRM_Sqltasks_Action_PostSQL") {
                showError('The "' + $scope.formNameFromType(currentAction.type) + '" action cannot occur after this action.');
              } else if (topAction && (topAction.type === "CRM_Sqltasks_Action_SuccessHandler" || topAction.type === "CRM_Sqltasks_Action_ErrorHandler")) {
                showError('The "' + $scope.formNameFromType(currentAction.type) + '" action cannot occur after either of these actions: "'
                  + [$scope.formNameFromType("CRM_Sqltasks_Action_SuccessHandler"), $scope.formNameFromType("CRM_Sqltasks_Action_ErrorHandler")].join('", "')
                  + '".');
              }
              break;
            default:
              if (topAction &&
                (
                  topAction.type === "CRM_Sqltasks_Action_SuccessHandler" ||
                  topAction.type === "CRM_Sqltasks_Action_ErrorHandler" ||
                  topAction.type === "CRM_Sqltasks_Action_PostSQL"
                )
              ) {
                var actionTypeNames = [
                  $scope.formNameFromType("CRM_Sqltasks_Action_SuccessHandler"),
                  $scope.formNameFromType("CRM_Sqltasks_Action_ErrorHandler"),
                  $scope.formNameFromType("CRM_Sqltasks_Action_PostSQL"),
                ];
                showError('The "' + $scope.formNameFromType(currentAction.type) + '" action cannot occur after either of these actions: "' + actionTypeNames.join('", "') + '".');
              }
              break;
          }
          //hack to fix multiple-select2 after drag and drop
          $('.sql-task-multiple-select2 .content select').select2();
        }
      };

      CRM.api3("Sqltask", "gettaskactions").done(function(result) {
        $scope.actions = result.values;
        $scope.$apply();
      });

      CRM.api3("Sqltaskfield", "getschedulingoptions").done(function(result) {
        $scope.schedulingOptions = result.values[0];
        var defaultOption = Object.keys(result.values[0])[0];
        if (defaultOption === "always" && !Number(taskId)) {
          $scope.taskOptions.scheduled = defaultOption;
          $scope.taskOptions.enabled = 0;
          $scope.taskOptions.parallel_exec = '0';
          $scope.taskOptions.input_required = 0;
          $scope.taskOptions.abort_on_error = '1';
          $scope.config = Object.assign($scope.config, {
            scheduled_month: 1,
            scheduled_weekday: 1,
            scheduled_day: 1
          });
        }
        loaderService.updateExecutionBlock();
        $scope.$apply();
      });

      $scope.addAction = function(actionName) {
        if (actionName === undefined) {
          return;
        }
        var newActionItem = {type: actionName};

        //add to the stack of similar action types:
        for (let index = $scope.config.actions.length - 1; index >= 0; index--) {
          if (actionName === $scope.config.actions[index].type) {
            $scope.config.actions.splice(index + 1, 0, newActionItem);
            return;
          }
        }

        if (actionName === "CRM_Sqltasks_Action_RunSQL" || actionName === "CRM_Sqltasks_Action_PostSQL") {
          newActionItem['enabled'] = "1";
        }

        var postSqlActionIndexes = [];
        var successHandlerActionIndexes = [];
        var errorHandlerActionIndexes = [];

        for (let index = 0; index < $scope.config.actions.length; index++) {
          if ("CRM_Sqltasks_Action_PostSQL" === $scope.config.actions[index].type) {
            postSqlActionIndexes.push(index);
          } else if ("CRM_Sqltasks_Action_SuccessHandler" === $scope.config.actions[index].type) {
            successHandlerActionIndexes.push(index);
          } else if ("CRM_Sqltasks_Action_ErrorHandler" === $scope.config.actions[index].type) {
            errorHandlerActionIndexes.push(index);
          }
        }

        switch (actionName) {
          case "CRM_Sqltasks_Action_PostSQL":
            if (successHandlerActionIndexes.length === 0 && errorHandlerActionIndexes.length === 0) {
              $scope.config.actions.push(newActionItem);
            } else if(successHandlerActionIndexes.length > 0) {
              $scope.config.actions.splice(successHandlerActionIndexes[0], 0, newActionItem);
            } else if(errorHandlerActionIndexes.length > 0) {
              $scope.config.actions.splice(errorHandlerActionIndexes[0], 0, newActionItem);
            }
            break;
          case "CRM_Sqltasks_Action_SuccessHandler":
            if (errorHandlerActionIndexes.length === 0) {
              $scope.config.actions.push(newActionItem);
            } else if(errorHandlerActionIndexes.length > 0) {
              $scope.config.actions.splice(errorHandlerActionIndexes[0], 0, newActionItem);
            }
            break;
          case "CRM_Sqltasks_Action_ErrorHandler":
            $scope.config.actions.push(newActionItem);
            break;
          default:
            var mixedActionIndexes = [].concat(postSqlActionIndexes).concat(successHandlerActionIndexes).concat(errorHandlerActionIndexes);
            if (mixedActionIndexes.length > 0) {
              $scope.config.actions.splice(Math.min.apply(Math, mixedActionIndexes), 0, newActionItem);
            } else {
              $scope.config.actions.push(newActionItem);
            }
            break;
        }
      };

      $scope.formNameFromType = function(type) {
        switch (type) {
          case "CRM_Sqltasks_Action_RunSQL":
            return ts("Run SQL Script");
          case "CRM_Sqltasks_Action_CreateActivity":
            return ts("Create Activity");
          case "CRM_Sqltasks_Action_APICall":
            return ts("API Call");
          case "CRM_Sqltasks_Action_CSVExport":
            return ts("CSV Export");
          case "CRM_Sqltasks_Action_SyncTag":
            return ts("Synchronise Tag");
          case "CRM_Sqltasks_Action_SyncGroup":
            return ts("Synchronise Group");
          case "CRM_Sqltasks_Action_CallTask":
            return ts("Run SQL Task(s)");
          case "CRM_Sqltasks_Action_PostSQL":
            return ts("Run Cleanup SQL Script");
          case "CRM_Sqltasks_Action_SuccessHandler":
            return ts("Success Handler");
          case "CRM_Sqltasks_Action_ErrorHandler":
            return ts("Error Handler");
          case "CRM_Sqltasks_Action_SegmentationAssign":
            return ts("Assign to Campaign (Segmentation)");
          case "CRM_Sqltasks_Action_SegmentationExport":
            return ts("Segmentation Export");
          case "CRM_Sqltasks_Action_RunPHP":
            return ts("Run PHP Code");
          default:
            return "";
        }
      };

      $scope.shouldShowTimeFieldsByName = function(fieldName) {
        if (!$scope.taskOptions.scheduled) {
          return false;
        }
        switch (fieldName) {
          case "minute":
            return $scope.taskOptions.scheduled !== "always";
          case "hour":
            return !["always", "hourly"].includes($scope.taskOptions.scheduled);
          case "day":
            return !["always", "hourly", "daily", "weekly"].includes(
              $scope.taskOptions.scheduled
            );
          case "weekday":
            return $scope.taskOptions.scheduled === "weekly";
          case "month":
            return $scope.taskOptions.scheduled === "yearly";
          default:
            return false;
        }
      };

      async function loadConfigTemplate () {
        // Extract template ID from query parameters
        let templateId = $location.search().template;

        // If query parameter 'template' does not exist, get default template ID from API
        if (!templateId) {
          templateId = await new Promise(resolve => {
            CRM.api3("Setting", "getvalue", { name: "sqltasks_default_template" }).done(result => {
              if (result.is_error) throw new Error(result.error_message);
              resolve(result.result);
            });
          }).catch(console.error);
        }

        if (!templateId) return;

        // Load template data from API
        CRM.api3("SqltaskTemplate", "get", { id: templateId }).done(result => {
          if (result.is_error) {
            console.error(result.error_message);
            return;
          }

          const template = JSON.parse(result.values.config);
          $scope.config = template.config;
          $scope.taskOptions.description = template.description;
          $scope.taskOptions.category = template.category;
          $scope.taskOptions.scheduled = template.scheduled;
          $scope.taskOptions.parallel_exec = template.parallel_exec;
          $scope.taskOptions.run_permissions = template.run_permissions;
          $scope.taskOptions.input_required = template.input_required;
          $scope.taskOptions.abort_on_error = template.abort_on_error;
          $scope.$apply();
          $scope.updateExecutionSchedule();
        });
      }
    });

  function removeItemFromArray(index) {
    this.$parent.config.actions.splice(index, 1);
  }

  function getBooleanFromNumber(number) {
    return !!Number(number);
  }

  function openCheckedActions() {
    CRM.$(function($) {
      var inputArray = $('.crm-accordion-header .input-checkbox input');
      $(inputArray).each(function() {
        if ($(this).is(":checked")) {
          var parent = $(this).closest(".crm-accordion-wrapper");
          if ($(parent).is(".collapsed")) {
            $(parent).removeClass("collapsed");
            $(parent)
              .find(".crm-accordion-body")
              .css("display", "block");
          }
        }
      });
      $('#sqlTasksActionsBlock').removeClass('loading');
    });
  }

  angular.module(moduleName).directive("runSql", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/RunSQL.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope) {
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
        $scope.onSqlScriptPress = function() {
          CRM.help("SQL Script", {
            id: "id-sql-script",
            file: "CRM/Sqltasks/Action/RunSQL"
          });
          return false;
        };
      }
    };
  });

  angular.module(moduleName).directive("apiCall", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/APICall.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {;
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };

        if ($scope.model.handle_api_errors === undefined) {
          // apply backend default in UI
          $scope.model.handle_api_errors = 'log_only';
        }

        $scope.handleApiErrorsOptions = [];
        CRM.api3("Sqltaskfield", "get_handle_api_errors_options", {
          sequential: 1,
          options: {limit : 0}
        }).done(function(result) {
          if (!result.is_error) {
            var handleApiErrorsOptions = [];
            Object.keys(result.values[0]).map(key => {
              var entity = result.values[0][key];
              if (key) {
                handleApiErrorsOptions.push({
                  value: key,
                  name: entity
                });
              }
            });
            $scope.handleApiErrorsData = handleApiErrorsOptions;
            loaderService.setDataLoaded('handle_api_errors_index_' + $scope.index);
            $scope.$apply();
          }
        });
      }
    };
  });

  angular.module(moduleName).directive("createActivity", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/CreateActivity.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };
        $scope.campaignData = [];
        CRM.api3("Campaign", "get", {
          sequential: 1,
          return: ["id", "title"],
          is_active: 1,
          options: { limit: 0, sort: "title ASC" }
        }).done(function(result) {
          if (!result.is_error) {
            var campaignData = [];
            result.values.map(type => {
              campaignData.push({
                value: type.id,
                name: type.title
              });
            });
            $scope.campaignData = campaignData;
            loaderService.setDataLoaded('activity_campaign_id_' + $scope.index);
            $scope.$apply();
          }
        });

        $scope.ts = CRM.ts();
        CRM.api3("OptionValue", "get", {
          sequential: 1,
          return: ["value", "label"],
          option_group_id: "activity_type",
          options: { limit: 0 },
          is_active: 1
        }).done(function(result) {
          var activityTypeData = [];
          if (!result.is_error) {
            result.values.map(type => {
              activityTypeData.push({
                value: type.value,
                name: type.label
              });
            });
          }
          loaderService.setDataLoaded('activity_activity_type_id_' + $scope.index);
          $scope.activityTypeData = activityTypeData;
          $scope.$apply();
        });

        CRM.api3("OptionValue", "get", {
          sequential: 1,
          return: ["value", "label"],
          option_group_id: "activity_status",
          options: { limit: 0 },
          is_active: 1
        }).done(function(result) {
          var statusData = [];
          if (!result.is_error) {
            result.values.map(type => {
              statusData.push({
                value: type.value,
                name: type.label
              });
            });
          }
          $scope.statusData = statusData;
          loaderService.setDataLoaded('activity_status_id' + $scope.index);
          $scope.$apply();
        });

        CRM.api3("OptionValue", "get", {
          sequential: 1,
          return: ["value", "label"],
          option_group_id: "priority",
          options: { limit: 0 },
          is_active: 1
        }).done(function(result) {
          var priorityData = [];
          if (!result.is_error) {
            result.values.map(type => {
              priorityData.push({
                value: type.value,
                name: type.label
              });
            });
          }
          $scope.priorityData = priorityData;
          loaderService.setDataLoaded('activity_priority_id' + $scope.index);
          $scope.$apply();
        });

        CRM.api3("OptionValue", "get", {
          sequential: 1,
          return: ["value", "label"],
          option_group_id: "engagement_index",
          options: { limit: 0 },
          is_active: 1
        }).done(function(result) {
          var engagementIndexData = [];
          if (!result.is_error) {
            result.values.map(type => {
              engagementIndexData.push({
                value: type.value,
                name: type.label
              });
            });
          }
          $scope.engagementIndexData = engagementIndexData;
          loaderService.setDataLoaded('activity_engagement_level' + $scope.index);
          $scope.$apply();
        });

        CRM.api3("OptionValue", "get", {
          sequential: 1,
          return: ["value", "label"],
          option_group_id: "encounter_medium",
          options: { limit: 0 },
          is_active: 1
        }).done(function(result) {
          var mediumData = [];
          if (!result.is_error) {
            result.values.map(type => {
              mediumData.push({
                value: type.value,
                name: type.label
              });
            });
          }
          $scope.mediumData = mediumData;
          loaderService.setDataLoaded('activity_medium_id' + $scope.index);
          $scope.$apply();
        });

        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
      }
    };
  });

  angular.module(moduleName).directive("csvExport", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/CSVExport.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };

        CRM.api3("Sqltaskfield", "getmessagetemplates").done(function(result) {
          if (!result.is_error) {
            var messageTemplateOptions = [];
            Object.keys(result.values[0]).map(key => {
              messageTemplateOptions.push({
                value: key,
                name: result.values[0][key]
              });
            });
            $scope.messageTemplateOptions = messageTemplateOptions;
            loaderService.setDataLoaded('csv_email_template' + $scope.index);
            $scope.$apply();
          }
        });

        CRM.api3("Sqltaskfield", "getfileencoding").done(function(result) {
          var encodingData = [];
          Object.keys(result.values[0]).map(key => {
            var entity = result.values[0][key];
            if (key) {
              encodingData.push({
                value: key,
                name: entity
              });
            }
          });
          $scope.encodingData = encodingData;
          loaderService.setDataLoaded('csv_encoding_' + $scope.index);
          $scope.$apply();
        });

        CRM.api3("Sqltaskfield", "getdelimiter").done(function(result) {
          delimiterData = [];
          Object.keys(result.values[0]).map(key => {
            var entity = result.values[0][key];
            if (key) {
              delimiterData.push({
                value: key,
                name: entity
              });
            }
          });
          delimiterData.push({
            value: "other",
            name: "other"
          });
          $scope.delimiterData = delimiterData;
          loaderService.setDataLoaded('csv_delimiter' + $scope.index);
          $scope.$apply();
        });

        CRM.api3("Sqltaskfield", "getenclosuremodes").done(result => {
          $scope.enclosureOptions = result.values[0].map(mode => ({ value: mode, name: mode }));
          loaderService.setDataLoaded('csv_enclosure_' + $scope.index);
          $scope.$apply();
        });
      }
    };
  });

  angular.module(moduleName).directive("syncTag", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/SyncTag.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };
        $scope.ts = CRM.ts();

        CRM.api3("Tag", "get", {
          sequential: 1,
          return: ["name", "id"],
          is_enabled: 1,
          options: { limit: 0 }
        }).done(function(result) {
          var tagsData = [];
          result.values.map(tag => {
            tagsData.push({
              value: tag.id,
              name: tag.name
            });
          });
          $scope.tagsData = tagsData;
          loaderService.setDataLoaded('tag_tag_id_' + $scope.index);
          $scope.$apply();
        });

        CRM.api3("Sqltaskfield", "getsynctagentities").done(function(result) {
          var entityData = [];
          Object.keys(result.values[0]).map(key => {
            var entity = result.values[0][key];
            if (key) {
              entityData.push({
                value: key,
                name: entity
              });
            }
          });
          $scope.entityData = entityData;
          loaderService.setDataLoaded('tag_entity_table' + $scope.index);
          $scope.$apply();
        });
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
      }
    };
  });

  angular.module(moduleName).directive("syncGroup", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/SyncGroup.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };
        $scope.ts = CRM.ts();

        CRM.api3("Group", "get", {
          sequential: 1,
          return: ["id", "title"],
          is_active: 1,
          options: { limit: 0 }
        }).done(function(result) {
          var groupData = [];
          result.values.map(group => {
            groupData.push({
              value: group.id,
              name: group.title
            });
          });
          $scope.groupData = groupData;
          loaderService.setDataLoaded('group_group_id' + $scope.index);
          $scope.$apply();
        });
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
      }
    };
  });

  angular.module(moduleName).directive("callTask", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/CallTask.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };
        $scope.isDataExists = function(array) {
          return Boolean(array && array.length);
        };

        var tasksData = [];
        var categoriesData = [];

        CRM.api3("Sqltaskfield", "gettaskcategories").done(function(result) {
          categoriesData = [];
          Object.keys(result.values[0]).map(key => {
            var category = result.values[0][key];
            if (key) {
              categoriesData.push({
                value: key,
                name: category
              });
            }
          });
          $scope.categoriesData = categoriesData;
          loaderService.setDataLoaded('task_categories_' + $scope.index);
          $scope.$apply();
        });

        CRM.api3("Sqltaskfield", "getexecutiontasks").done(function(result) {
          tasksData = [];
          if (!result.is_error) {
            Object.keys(result.values[0]).map(key => {
              var task = result.values[0][key];
              tasksData.push({
                value: key,
                name: task
              });
            });
            $scope.tasksData = tasksData;
            loaderService.setDataLoaded('task_tasks_' + $scope.index);
            $scope.$apply();
          }
        });
      }
    };
  });

  angular.module(moduleName).directive("postSql", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/PostSQL.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope) {
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
      }
    };
  });

  angular.module(moduleName).directive("runPhp", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/RunPHP.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope) {
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
        $scope.onPhpCodePress = function() {
          CRM.help("PHP Code", {
            id: "id-php-code",
            file: "CRM/Sqltasks/Action/RunPHP"
          });
          return false;
        };
      }
    };
  });

  function onInfoPress(entity, id, file) {
    CRM.help(entity, {
      id: id,
      file: file
    });
    return false;
  }

  angular.module(moduleName).directive("successHandler", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/SuccessHandler.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };

        CRM.api3("Sqltaskfield", "getmessagetemplates").done(function(result) {
          if (!result.is_error) {
            var messageTemplateOptions = [];
            Object.keys(result.values[0]).map(key => {
              messageTemplateOptions.push({
                value: key,
                name: result.values[0][key]
              });
            });
            $scope.messageTemplateOptions = messageTemplateOptions;
            loaderService.setDataLoaded('success_email_template' + $scope.index);
            $scope.$apply();
          }
        });

        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
      }
    };
  });

  angular.module(moduleName).directive("errorHandler", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/ErrorHandler.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };

        CRM.api3("Sqltaskfield", "getmessagetemplates").done(function(result) {
          if (!result.is_error) {
            var messageTemplateOptions = [];
            Object.keys(result.values[0]).map(key => {
              messageTemplateOptions.push({
                value: key,
                name: result.values[0][key]
              });
            });
            $scope.messageTemplateOptions = messageTemplateOptions;
            loaderService.setDataLoaded('email_template' + $scope.index);
            $scope.$apply();
          }
        });

        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;
      }
    };
  });

  angular.module(moduleName).directive("segmentationAssign", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/SegmentationAssign.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };
        $scope.campaignData = [];
        CRM.api3("Campaign", "get", {
          sequential: 1,
          return: ["id", "title"],
          is_active: 1,
          options: { limit: 0, sort: "title ASC" }
        }).done(function(result) {
          if (!result.is_error) {
            var campaignData = [];
            result.values.map(type => {
              campaignData.push({
                value: type.id,
                name: type.title
              });
            });
            $scope.campaignData = campaignData;
            loaderService.setDataLoaded('segmentation_assign_campaign_id' + $scope.index);
            $scope.$apply();
          }
        });
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;

        $scope.checkboxChange = function(value) {
          if (value == "1") {
            this.model.segment_name = "";
          }
        };

        $scope.statusChanged = function() {
          this.model.segment_order_table = "";
          this.model.segment_order = "";
        };

        var statusesData = [];

        CRM.api3("Sqltaskfield", "get_campaign_statuses").done(function(
          result
        ) {
          statusesData = [];
          Object.keys(result.values[0]).map(key => {
            var status = result.values[0][key];
            if (key) {
              statusesData.push({
                value: key,
                name: status
              });
            }
          });
          $scope.statusesData = statusesData;
          loaderService.setDataLoaded('segmentation_assign_start' + $scope.index);
          $scope.$apply();
        });
      }
    };
  });

  angular.module(moduleName).directive("segmentationExport", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/SegmentationExport.html",
      scope: {
        model: "=",
        index: "<"
      },
      controller: function($scope, loaderService) {
        $scope.isDataLoaded = function(elementId) {
          return loaderService.isDataLoaded(elementId);
        };
        $scope.campaignData = [];
        CRM.api3("Campaign", "get", {
          sequential: 1,
          return: ["id", "title"],
          is_active: 1,
          options: { limit: 0, sort: "title ASC" }
        }).done(function(result) {
          if (!result.is_error) {
            var campaignData = [];
            result.values.map(type => {
              campaignData.push({
                value: type.id,
                name: type.title
              });
            });
            $scope.campaignData = campaignData;
            loaderService.setDataLoaded('segmentation_export_campaign_id' + $scope.index);
            $scope.$apply();
          }
        });
        $scope.ts = CRM.ts();
        $scope.removeItemFromArray = removeItemFromArray;
        $scope.getBooleanFromNumber = getBooleanFromNumber;
        $scope.onInfoPress = onInfoPress;

        $scope.checkboxChange = function(value) {
          if (value == "1") {
            this.model.date_from = "";
            this.model.date_to = "";
          }
        };

        if ($scope.model && getBooleanFromNumber($scope.model.campaign_id)) {
          CRM.api3("Segmentation", "segmentlist", {
            campaign_id: $scope.model.campaign_id
          }).done(function(result) {
            var segmentationData = [];
            Object.keys(result.values).map(key => {
              var entity = result.values[key];
              if (key) {
                segmentationData.push({
                  value: key,
                  name: entity
                });
              }
            });
            $scope.segmentationData = segmentationData;
            loaderService.setDataLoaded('segmentation_export_segments' + $scope.index);
            $scope.$apply();
          });
        }

        CRM.api3("Sqltaskfield", "getmessagetemplates").done(function(result) {
          if (!result.is_error) {
            var messageTemplateOptions = [];
            Object.keys(result.values[0]).map(key => {
              messageTemplateOptions.push({
                value: key,
                name: result.values[0][key]
              });
            });
            $scope.messageTemplateOptions = messageTemplateOptions;
            loaderService.setDataLoaded('segmentation_export_email_template' + $scope.index);
            $scope.$apply();
          }
        });

        $scope.statusChanged = function(value, fieldId) {
          CRM.$(function($) {
            $scope.model.segments = "";
            var inputStyles =  {
              'width' : '100%',
              'max-width' : '300px',
              'font-family' : 'monospace, monospace !important',
              'box-sizing' : 'border-box',
              'height' : '28px'
            };
            setTimeout(() => {
              $("#" + fieldId)
                .css(inputStyles)
                .select2();
            }, 0);
          });
          if (getBooleanFromNumber(value)) {
            CRM.api3("Segmentation", "segmentlist", {
              campaign_id: value
            }).done(function(result) {
              var segmentationData = [];
              Object.keys(result.values).map(key => {
                var entity = result.values[key];
                if (key) {
                  segmentationData.push({
                    value: key,
                    name: entity
                  });
                }
              });
              $scope.segmentationData = segmentationData;
              $scope.$apply();
            });
          }
        };

        CRM.api3("Sqltaskfield", "get_segmentation_exporter").done(function(result) {
          exporterData = [];
          Object.keys(result.values[0]).map(key => {
            var entity = result.values[0][key];
            if (key) {
              exporterData.push({
                value: key,
                name: entity
              });
            }
          });
          $scope.exporterData = exporterData;
          loaderService.setDataLoaded('segmentation_export_exporter' + $scope.index);
          $scope.$apply();
        });
      }
    };
  });

  // Components
  angular.module(moduleName).directive("textArea", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/textArea.html",
      scope: {
        isRequired: "<isrequired",
        componentModel: "=model",
        fieldLabel: "<fieldlabel",
        fieldId: "<fieldid",
        rowsNumber: "<rowsnumber",
        helpAction: "&helpaction",
        showHelpIcon: "<showhelpicon",
        columnsNumber: "<columnsnumber",
        inputMaxWidth: "<inputmaxwidth",
      },
      controller: function($scope) {
        $scope.columnsNumber = angular.isDefined($scope.columnsNumber) ? $scope.columnsNumber : 74;
        $scope.inputMaxWidth = angular.isDefined($scope.inputMaxWidth) ? $scope.inputMaxWidth : "300px";
        $scope.textAreaStyles = {
          'width' : $scope.inputMaxWidth,
          'font-family' : 'monospace, monospace !important',
          'box-sizing' : 'border-box',
        };
      }
    };
  });

  angular.module(moduleName).directive("textInput", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/textInput.html",
      scope: {
        isRequired: "<isrequired",
        componentModel: "=model",
        fieldLabel: "<fieldlabel",
        fieldId: "<fieldid",
        helpAction: "&helpaction",
        showHelpIcon: "<showhelpicon",
        sizeLength: "<sizelength",
        extraText: "<extratext",
        isDisabled: "<disabled",
        inputMaxWidth: "<inputmaxwidth",
      },
      controller: function($scope) {
        $scope.isDisabled = angular.isDefined($scope.isDisabled) ? $scope.isDisabled : false;
        $scope.extraText = angular.isDefined($scope.extraText) ? $scope.extraText : "";
        $scope.componentModel = angular.isDefined($scope.isDisabled) ? $scope.componentModel : "";
        $scope.sizeLength = angular.isDefined($scope.sizeLength) ? $scope.sizeLength : 32;
        $scope.inputMaxWidth = angular.isDefined($scope.inputMaxWidth) ? $scope.inputMaxWidth : "300px";
        $scope.inputStyle =  {
          'width' : '100%',
          'max-width' : $scope.inputMaxWidth,
          'font-family' : 'monospace, monospace !important',
          'box-sizing' : 'border-box',
          'height' : '28px'
        };
      }
    };
  });

  angular.module(moduleName).directive("checkBox", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/checkBox.html",
      scope: {
        isRequired: "<isrequired",
        componentModel: "=model",
        fieldLabel: "<fieldlabel",
        fieldId: "<fieldid",
        helpAction: "&helpaction",
        showHelpIcon: "<showhelpicon",
        checkboxChange: "&"
      }
    };
  });

  angular.module(moduleName).directive("ordinarySelect", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/ordinarySelect.html",
      scope: {
        isRequired: "<isrequired",
        componentModel: "=model",
        fieldLabel: "<fieldlabel",
        fieldId: "<fieldid",
        optionsArray: "<optionsarray",
        helpAction: "&helpaction",
        showHelpIcon: "<showhelpicon",
        inputMaxWidth: "<inputmaxwidth",
      },
      controller: function($scope) {
        $scope.inputMaxWidth = angular.isDefined($scope.inputMaxWidth) ? $scope.inputMaxWidth : "300px";
        $scope.selectStyle = {
          'width' : '100%',
          'max-width' : $scope.inputMaxWidth,
          'box-sizing' : 'border-box',
          'height' : '28px'
        };
      }
    };
  });

  angular.module(moduleName).directive("ordinarySelect2", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/ordinarySelect2.html",
      scope: {
        isRequired: "<isrequired",
        componentModel: "=model",
        fieldLabel: "<fieldlabel",
        fieldId: "<fieldid",
        isDataLoaded: "<isdataloaded",
        optionsArray: "<optionsarray",
        helpAction: "&helpaction",
        showHelpIcon: "<showhelpicon",
        selectChange: "&",
        inputMaxWidth: "<inputmaxwidth",
        fieldIdToChange: "<"
      },
      controller: function($scope) {
        $scope.inputMaxWidth = angular.isDefined($scope.inputMaxWidth) ? $scope.inputMaxWidth : "300px";
        var selectStyles = {
          'width' : "100%",
          'max-width' : $scope.inputMaxWidth,
          'box-sizing' : 'border-box',
          'height' : '28px'
        };

        if (angular.isDefined($scope.isDataLoaded) && $scope.isDataLoaded == false) {
          var timerId = setInterval(function() {
            if ($scope.isDataLoaded) {
              $("#" + $scope.fieldId).css(selectStyles).select2();
              clearInterval(timerId);
            }
          }, 300);
        } else {
          CRM.$(function($) {
            setTimeout(function() {
              $("#" + $scope.fieldId).css(selectStyles).select2();
            }, 1500);
          });
        }
      }
    };
  });

  angular.module(moduleName).directive("multipleSelect2", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/multipleSelect2.html",
      scope: {
        isRequired: "<isrequired",
        componentModel: "=model",
        fieldLabel: "<fieldlabel",
        isDataLoaded: "<isdataloaded",
        fieldId: "<fieldid",
        optionsArray: "<optionsarray",
        helpAction: "&helpaction",
        showHelpIcon: "<showhelpicon",
        inputMaxWidth: "<inputmaxwidth",
      },
      controller: function($scope) {
        $scope.inputMaxWidth = angular.isDefined($scope.inputMaxWidth) ? $scope.inputMaxWidth : "300px";
        var selectStyles = {
          'width' : "100%",
          'max-width' : $scope.inputMaxWidth,
          'box-sizing' : 'border-box',
        };
        if (angular.isDefined($scope.isDataLoaded) && $scope.isDataLoaded == false) {
          var timerId = setInterval(function() {
            if ($scope.isDataLoaded) {
              $("#" + $scope.fieldId).css(selectStyles).select2();
              clearInterval(timerId);
            }
          }, 300);
        } else {
          CRM.$(function($) {
            setTimeout(function() {
              $("#" + $scope.fieldId).css(selectStyles).select2();
            }, 1500);
          });
        }
      }
    };
  });

  angular.module(moduleName).directive("selectEntityref", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/selectEntityref.html",
      scope: {
        isRequired: "<isrequired",
        componentModel: "=model",
        fieldLabel: "<fieldlabel",
        fieldId: "<fieldid",
        dataParams: "<dataparams",
        helpAction: "&helpaction",
        showHelpIcon: "<showhelpicon",
        inputMaxWidth: "<inputmaxwidth",
      },
      controller: function($scope) {
        $scope.inputMaxWidth = angular.isDefined($scope.inputMaxWidth) ? $scope.inputMaxWidth : "300px";
        var selectStyles = {
          'width' : "100%",
          'max-width' : $scope.inputMaxWidth,
          'box-sizing' : 'border-box',
          'height' : '28px'
        };
        $scope.dataParams = angular.isDefined($scope.dataParams) ? $scope.dataParams : [];
        CRM.$(function($) {
          setTimeout(function() {
            $("#" + $scope.fieldId).css(selectStyles).crmEntityRef();
          }, 0);
        });
      }
    };
  });

  angular.module(moduleName).directive("actionAdditionalInfo", function() {
    return {
      restrict: "E",
      templateUrl: "~/sqlTaskConfigurator/actionAdditionalInfo.html",
      scope: {
        model: "=",
        index: "<",
        fieldPrefix: "<fieldprefix",
      },
      controller: function($scope) {
        $scope.ts = CRM.ts();
        $scope.onInfoPress = onInfoPress;
        $scope.isShowEditForm = false;
        $scope.toggleShowingEditForm = function(event) {
          $scope.isShowEditForm = !$scope.isShowEditForm;
          if ($scope.isShowEditForm) {
            CRM.$(event.currentTarget).closest('.sql-task-action-addition-info-wrap').find('.sql-task-action-addition-info-edit-form-wrap').slideDown("fast");
          } else {
            CRM.$(event.currentTarget).closest('.sql-task-action-addition-info-wrap').find('.sql-task-action-addition-info-edit-form-wrap').hide("fast");
          }
        };
      }
    };
  });

})(angular, CRM.$, CRM._);
