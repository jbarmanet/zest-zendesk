<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use App\Models\Integration;
use App\Models\User;
use App\Models\Tag;
use App\Models\Contact;
use App\Services\ZendeskService;
use Illuminate\Support\Facades\Storage;
use App\Events\IntegrationEvent;

class ZendeskController extends Controller
{
  protected $user;

  public function __construct()
  {
    $this->middleware(function ($request, $next) {
      $this->user = Auth::user();
      return $next($request);
    });
  }

  
  
  

  /**** Zendesk App Stuff ******/
  
  /***** ATHENTICATE when app loads *****/

  public function app_auth(Request $request, $subdomain)
  {
    if ($request->has('secret')) {
      if ($integration = Integration::where('name', 'zendesk')
          ->where('identifier', $subdomain)
          ->where('secret', $request->get('secret'))
          ->latest('updated_at')->first())
      {
        // Save random ticket Id only one time
        if ($request->has('tid') && $request->has('rid')) {
          if ($request->get('tid') != 0 && $request->get('rid') != '') {
            if ($integration->team->get_data("zendesk_ticket_".$request->get('tid')) == NULL) {
              $integration->team->save_data("zendesk_ticket_".$request->get('tid'), $request->get('rid'));
            }
          }
        }
        return response()->json(['team_slug' => $integration->team->slug, 'team_slugname' => $integration->team->slugname, 'integration' => $integration->id], 200);
      }
    }
    return response('Unauthorized', 401);
  }
  
  



  /***** Serve App Views *****/


  public function support_ticket_sidebar(Request $request)
  {
    $params = [
      'product' => 'support',
      'location' => 'ticket_sidebar'
    ];
    return view('integrations/zendesk/app', $params);
  }
  
  public function support_ticket_editor(Request $request)
  {
    $params = [
      'product' => 'support',
      'location' => 'ticket_editor'
    ];
    return view('integrations/zendesk/app', $params);
  }
  
  
  
  /*********** API to Serve app ajax requests *******/ 
  

  public function app_connect(Request $request) {
    if ($request->has('appId') && $request->has('subdomain') && $request->has('installationId')) {
      $integration = Integration::where('metadata','like','%'.$request->get('appId').'%')
          ->where('metadata','like','%'.$request->get('installationId').'%')
          ->where('name','zendesk')
          ->where('identifier',$request->get('subdomain'))->firstOrFail();
      $data = [
        'integration' => $integration->id,
        'team_slug' => $integration->team->slug,
        //'team_id' => $integration->team->id,
      ];
      return response()->json($data, 200);
    }
    return response('App Not Found', 404);

  }


  public function get_videos(Request $request)
  {
    $page = 1;
    if ($request->has('page')) {
      $page = $request->get('page');
    }

    $videos = [];

    if ($request->has(['secret', 'team_slug', 'subdomain', 'page']))
    {
      if ($integration = Integration::where('identifier', $request->get('subdomain'))
        ->where('name', 'zendesk')
        ->where('secret', $request->get('secret'))
        ->latest('updated_at')->first()) {
        $team = $integration->team;
        if ($team->slug !== $request->get('team_slug')) {
          return response('Unauthorized', 401);
        }

        $zendesk_contact_id = $request->get('contact_id');
        $zendesk_contact_external_id = $zendesk_contact_id;
        if ($request->has('contact_external_id')) {
          $zendesk_contact_external_id = $request->get('contact_external_id');
        }
        $zendesk_contact_email = NULL;
        if ($request->has('contact_email')) {
          $zendesk_contact_email = $request->get('contact_email');
        }
        $contact = Contact::where('team_id', $team->id)->where(
          function($query) use ($zendesk_contact_id,$zendesk_contact_external_id,$zendesk_contact_email) {
            if (strlen($zendesk_contact_id) > 0)
              $query->where('identifier', $zendesk_contact_id);
            if (strlen($zendesk_contact_external_id) > 0)
              $query->orWhere('identifier', $zendesk_contact_external_id);
            if (strlen($zendesk_contact_email) > 0)
              $query->orWhere('email', $zendesk_contact_email);
        })->latest('created_at')->first();

        if ($contact) {
          $videos = $contact->videos()->orderBy('created_at', 'desc')->simplePaginate(5, ['*'], 'page', $page);
        }
      }

      // debug get any video
      // $videos = $team->videos()->orderBy('created_at', 'desc')->simplePaginate(5, ['*'], 'page', $page);
      foreach($videos as $video) {
        $video->added_diff = $video->created_at->diffForHumans();
        $video->added_duration = $this->getDuration($video->video_length);
        $video->added_date = $video->created_at->format('M j, Y \a\t g:ia');
      }
      return response()->json($videos, 200);
    }
    return response('Missing parameters', 401);
  }


  private function getDuration($millisec = NULL) {
    if ($millisec == NULL) {
      return NULL;
    }
    $time = round($millisec / 1000);
    $minutes = floor($time / 60);
    $seconds = $time - $minutes * 60;

    if ($seconds < 10) {
      $seconds = '0' . $seconds;
    }
    return $minutes . ':' . $seconds;
  }

  public function ask_for_a_video(Request $request, $ticket_id=NULL)
  {
    if ($ticket_id == NULL && !$request->get('contact_email')) {
      return response('Request must have a ticket id or user', 403);
    }
    if ($request->has(['link', 'agent_id', 'secret', 'team_slug', 'subdomain']))
    {
      if ($integration = Integration::where('identifier', $request->get('subdomain'))
        ->where('name', 'zendesk')
        ->where('secret', $request->get('secret'))
        ->latest('updated_at')->first()) {
        $team = $integration->team;
        if ($team->slug !== $request->get('team_slug')) {
          return response('Unauthorized', 401);
        }
        $zendesk = new ZendeskService($team);
        $comment = $this->comment_builder($request->get('link'));
        if ($result = $zendesk->post_comment($comment, $request->get('agent_id'), $ticket_id)) {
          return response('OK', 200);
        }
        return response('Something went wrong when posting comment', 500);
      }
    }
    return response('Missing parameters', 401);
  }

  private function comment_builder($link)
  {
    $comment = '<p>';
    $comment .= '<img src="' . config('app.url') . '/img/rec.png'. '" width="50">';
    $comment .= '<br />';
    $comment .= '<a href="'.$link.'" title="Please record your screen">';
    $comment .= 'Click here to record your screen';
    $comment .= '</a>';
    $comment .= '</p>';
    return $comment;
  }





}
