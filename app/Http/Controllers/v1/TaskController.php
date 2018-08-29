<?php

namespace App\Http\Controllers\v1;

use App\Comment;
use App\Http\Controllers\Controller;
use App\Log;
use App\Notification;
use App\Role;
use App\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Class TaskController
 *
 * @package App\Http\Controllers\v1
 */
class TaskController extends Controller
{
    /**
     * Get tasks list
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll()
    {
        try {
            $user = $this->validateSession();

            if ($user->role_id === Role::ROLE_USER) {
                $tasks = Task::where('assign', $user->id)->paginate(7);
            } else {
                $tasks = Task::paginate(7);
            }

            return $this->returnSuccess($tasks);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Create a task
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            $user = $this->validateSession();

            $rules = [
                'name' => 'required',
                'description' => 'required',
                'assign' => 'required|exists:users,id'
            ];

            $validator = Validator::make($request->all(), $rules);

            if (!$validator->passes()) {
                return $this->returnBadRequest('Please fill all required fields');
            }

            //create  the task
            $task = new Task();
            $task->name = $request->name;
            $task->description = $request->description;
            $task->status = Task::STATUS_ASSIGNED;
            $task->user_id = $user->id;
            $task->assign = $request->assign;
            $task->save();

            //send the notification to the assigned user
            $notification  = new Notification();
            $notification->user_id = $request->assign;
            $notification->message = 'Task ' . $task->name  . 'has been assigned to you';
            $notification->save();

            //save the log
            $log  = new Log();
            $log->task_id = $task->id;
            $log->user_id = $user->id;
            $log->type = Log::ASSINGN_UPDATE;
            $log->old_value = $user->id;;
            $log->new_value = $request->assign;
            $log->save();

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Update a task
     *
     * @param Request $request
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $this->validateSession();

            $task = Task::find($id);

            if ($user->role_id === Role::ROLE_USER && $user->id !== $task->assign) {
                return $this->returnError('You don\'t have permission to update this task');
            }

            if ($request->has('name')) {
                $task->name = $request->name;
            }

            if ($request->has('description')) {
                $task->description = $request->description;
            }

            if ($request->has('status')) {
                $log  = new Log();
                $log->task_id = $task->id;
                $log->user_id = $user->id;
                $log->type = Log::STATUS_UPDATE;
                $log->old_value = $task->status;
                $log->new_value = $request->status;
                $log->save();

                $task->status = $request->status;
            }

            if ($request->has('assign')) {
                $log  = new Log();
                $log->task_id = $task->id;
                $log->user_id = $user->id;
                $log->type = Log::ASSINGN_UPDATE;
                $log->old_value = $task->assign;
                $log->new_value = $request->assign;
                $log->save();

                $task->assign = $request->assign;

                //notify the assigned user about the tasks
                $notification  = new Notification();
                $notification->user_id = $request->assign;
                $notification->message = 'Task ' . $task->name  . ' has been assigned to you';
                $notification->save();

            }

            $task->save();

            return $this->returnSuccess('Task updated');
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    //add comments to a given task
    public function addComment(Request $request,$id)
    {
        try{
            $user = $this->validateSession();
            $task = Task::find($id);

            $rules = [
                'comment' => 'required|min:3',
            ];

            $validator = Validator::make($request->all(), $rules);

            if (!$validator->passes()) {
                return $this->returnBadRequest('Please fill all required fields');
            }

            $comment = new Comment();
            $comment->user_id = $user->id;
            $comment->task_id = $task->id;
            $comment->comment = $request->comment;
            $comment->save();

            return $this->returnSuccess('Comment posted');
        }catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }

    }

    //get comments for a given task
    public function getComments($id)
    {
        try{
            $task = Task::find($id);
            $task_comments  = $task->comments;

            return $this->returnSuccess($task_comments);
        }catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }



    public function getNotifications($id)
    {
        try{
            $user = User::find($id);
            $user_notifications  = $user->notfications;
            return $this->returnSuccess($user_notifications);
        }catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    //get all logs for a given task
    public function getLogs($id)
    {
        try{
            $task = Task::find($id);
            $logs  = $task->logs;
            return $this->returnSuccess($logs);
        }catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }

    /**
     * Delete a task
     *
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try {
            $user = $this->validateSession();

            if ($user->role_id !== Role::ROLE_ADMIN) {
                return $this->returnError('You don\'t have permission to delete this task');
            }

            $task = Task::find($id);

            $task->delete();

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage());
        }
    }
}