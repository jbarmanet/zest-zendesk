<template>

  <div :class="'box is-shadowless is-flex-grow-1 is-flex is-flex-direction-column '+product+' '+location">
    <!-- <p>{{ product }}, {{ location }} </p> -->
    <div v-if="!is_registered && location=='ticket_editor'" class="is-flex is-flex-grow-1">&nbsp;</div>
    <div v-if="is_loading" class="preStuff mt-6 mb-4">
      <b-spinner variant="secondary" small label="Loading…"></b-spinner>
    </div>
    <div v-else-if="has_match_error" class="preStuff mt-6 mb-4">
      <p><v-icon name="alert-triangle" style="width:17px;height:17px;margin-bottom:-2.5px;"></v-icon> Security error: Zendesk and Zest accounts do not match.</p>
      <p class="mt-4">You should uninstall this app and reinstall it with an Admin profile.</p>
    </div>
    <div v-else-if="!is_registered && agent_role != 'admin'" class="preStuff mt-6 mb-4">
      <p>You must sign in to your Zest account to complete installation.</p>
      <p>&nbsp;</p>
      <p>Actually you are using Zendesk with this role: <code>{{ agent_role }}</code>. You must use an <code>admin</code> role account the first time you connect with Zest.</p>
    </div>
    <div v-else-if="!is_registered && !has_zest_account" class="preStuff mt-6 mb-4">
      <p>Sign in to your Zest account to complete installation</p>
      <a class="button is-link mt-4" :href="login_url" target="_blank" @click="connectLinkClicked">
        <span>Sign in</span>
        <span class="icon is-small">
          <v-icon name="external-link" style="width:17px;height:17px;"></v-icon>
        </span>
      </a>
    </div>
    <div v-else-if="!is_registered && has_zest_account && !has_zest_integration" class="preStuff mt-6 mb-4">
      <p>You must terminate installation by connecting Zendesk to your Zest account</p>
      <a class="button is-link mt-4" :href="login_url" target="_blank" @click="connectLinkClicked">
        <span>Connect</span>
        <span class="icon is-small">
          <v-icon name="external-link" style="width:17px;height:17px;"></v-icon>
        </span>
      </a>
    </div>

    <div v-else class="stuffContainer">

      <div v-if="location == 'ticket_sidebar'">

        <div class="column px-0 py-0 is-full has-text-left sidebarlist">

          <a href="#" title="Ask the user for a recording" class="action ask py-3"
            @click.prevent="ask_for_a_recording()">
            <div class="block" v-if="!ask_recording_loading">
              <strong>Ask the user for a recording</strong>
            </div>
            <div class="block" v-else>
              <b-spinner variant="secondary" small label="Loading…"></b-spinner>
            </div>
          </a>

          <a :href="send_recording_url" title="Record a video and share it with the user" class="action record py-3"
            target="_blank">
            <div class="block">
              <strong>Record a video and share it with the user</strong>
            </div>
          </a>


          <div class="block mb-2 mt-4 title">
            <strong>Recorded by this user:</strong>
          </div>
          <div class="block mb-2 novideo has-text-grey ml-2" v-if="user_videos.length==0">
            no video received yet
          </div>
          <a v-else v-for="video in user_videos" :key="video.id" :href="spark.app_url + '/share/' + video.uuid" target="_blank" title="Open in new tab">
            <div class="block">
              <strong><span>{{ video.added_diff }}</span><span v-if="video.title">, {{ video.title }}</span></strong>
            </div>
            <div class="block has-text-grey ">
              {{ video.added_duration }} • {{ video.added_date }}
            </div>
          </a>
          <a v-if="user_videos.length > 0 && next_page>0" @click.prevent="get_videos_from_user()">
            <div class="block my-2">
              <span v-if="loading_videos">
                <b-spinner variant="secondary" small label="Loading…"></b-spinner>
              </span>
              <span v-else>
                + Load more…
              </span>
            </div>
          </a>

        </div>


      </div>

      <div v-else-if="location == 'ticket_editor'" class="is-flex is-flex-grow-1">

        <div class="column px-0 py-0 is-full has-text-left editor is-flex is-flex-grow-1 is-flex-direction-column is-justify-content-flex-end">

          <div class="editorPopTitle">Zest</div>

          <a href="https://www.notion.so/hellozest/Zest-for-Zendesk-Quickstart-407f8dc2e94d434bbf244ece2163f133" target="_blank" title="Visit Help Center" class="action help pt-3 pb-1 has-text-right">
            <div class="block">
              <strong>Extra Help</strong>
            </div>
          </a>


          <a href="#" title="Ask the user for a recording" class="action ask py-3" @click.prevent="ask_for_a_recording()" v-if="Object.keys(requester).length > 0">
            <div class="block">
              <strong>Ask the user for a recording</strong>
            </div>
          </a>

          <a :href="send_recording_url" title="Record a video and share it with the user" class="action record py-3"  v-if="Object.keys(requester).length > 0"
            target="_blank"
            :style="ticket.id ? '' : 'opacity:0.5'" @click="send_a_recording">
            <div class="block" v-if="ticket.id">
              <strong>Record a video and share it with the user</strong>
            </div>
            <div class="block" v-else>
              <span class="">You must save the ticket before sending a video</span>
            </div>
          </a>

          <div class="action no_requester px-3 py-4 has-text-grey" v-else>
            Before using Zest you need to add a recipient or requester to this ticket…
          </div>

        </div>

      </div>






    </div>


  </div>

</template>



<script>

import { debounce } from "debounce";

import { setKey, getKey, closePopup,
  getMetadata, getContext,
  //onAppActivated,onAppExpanded,onPaneActivated,
  //getTicket,getTicketComment,getTicketBrand,getTicketIsNew,
  getTicketDetails, getTicketId,
  getCurrentUser, getTicketRequester,
  getTicketFields,setTicketCustomField,hideField,getTicketField,
  insertCommentHtml, insertCommentText, insertCommentMarkdown,
  editorInsertHtml, editorInsertLink, editorInsertImage,
  //onTicketSubmitDone, onTicketSave,
  onTicketChanged } from './zaf';

export default {
  //name: "integration-zendesk",  // using EXACTLY this name is essential

  props: ['product','location'],

  components: {

  },

  created: function() {



  },

  beforeDestroy() {
    clearTimeout(this.test_loop);
  },

  data() {
    return {
      has_started_auth: false,
      is_loading: true,
      context:{},
      metadata:{},
      is_registered: false,
      has_zest_account: false,
      has_zest_integration: false,
      team_slug: "",
      team_slugname: "",
      integration_id: 0,
      subdomain: "",

      agent: {},
      requester: {},

      //brand_name: "",
      //brand_logo: "",

      test_loop: null,
      should_test_in_loop: false,

      has_match_error: false,
      ticket: {},

      loading_videos: false,
      next_page: 1,
      user_videos: [],

      ask_recording_loading: false,
      send_recording_loading: false,
    }
  },

  mounted: function() {
    //console.log(this.location);
    this.start_auth();

    onTicketChanged(this.on_ticket_changed);
    //onTicketSubmitDone(this.on_ticket_submit_done);

    // if (this.location = "ticket_editor") {
    //   onAppActivated(this.app_activated());
    //   onAppExpanded(this.app_expanded());
    //   onPaneActivated(this.pane_activated());
    // }

    // getTicketFields().then((response) => {
    //   console.log("getTicketFields", response)
    // })
    // getCurrentAccount().then((response) => {
    //   console.log("getCurrentAccount", response)
    // })
    // getTicketRequester().then((response) => {
    //   //let toto = JSON.stringify(response);
    //   //let tata = JSON.parse(toto);
    //   console.warn(response["ticket.requester"]);
    //   if (tata.ticket.requester) {
    //     this.requester = response.ticket.requester;
    //     console.log("YES requester",response.ticket.requester);
    //   } else {
    //     console.log("NO requester");
    //   }
    //   this.get_metadata();
    // });
    //onPaneActivated(this.pane_activated());

  },

  methods: {
    // pane_activated: function() {
    //   console.log("pane_activated")
    // },
    // app_activated: function() {
    //   console.log("app_activated")
    // },
    // app_expanded: function() {
    //   console.log("app_expanded")
    // },

    // on_ticket_save: function(e) {
    //   console.log("on_ticket_save")
    //   return getTicketId().then((data) => {
    //     this.ticket.id = data["ticket.id"]
    //     console.log("on_ticket_save ticket.id:",ticket["ticket.id"])
    //     setTimeout(function() {
    //       return true
    //     },1000);
    //     //return fetch('https://myapi.example.org/is_polite?comment=' + data['ticket.comment.text']).catch(function() {
    //       //throw 'You must be more polite';
    //     //});
    //   });

      /*return new Promise(function(resolve, reject) {
        getTicketId().then((ticket) => {
          console.log("on_ticket_save ticket.id:",ticket["ticket.id"])
          resolve(false);
        })*/
        /*
        $('#failMeNow').click(function(event){
          reject(false);
        });

        setTimeout(function(){
          done();
        }, 5000);*/

      //});
    // },
    // on_ticket_submit_done: function(e) {
    //   console.log("on_ticket_submit_done")
    //   getTicketId().then((ticket) => {
    //     this.ticket.id = ticket["ticket.id"]
    //     console.log("on_ticket_submit_done ticket.id:",ticket["ticket.id"])
    //     setTimeout(function() {
    //       return true
    //     },5000);
    //     //return true
    //   })
      //console.log(e)
      // if (e["propertyName"].substr(0, 17) == "ticket.requester.") {
      //   getTicketRequester().then((response) => {
      //     if (response["ticket.requester"]) {
      //       this.requester = response["ticket.requester"];
      //     } else {
      //       console.log("NO requester");
      //     }
      //   });
      // }
    // },
    on_ticket_changed: debounce(function(e) {
      //console.log("on_ticket_changed")
      //console.log(e)
      if (e["propertyName"].substr(0, 17) == "ticket.requester.") {
        getTicketRequester().then((response) => {
          if (response["ticket.requester"]) {
            this.requester = response["ticket.requester"];
          } else {
            console.log("NO requester");
          }
        });
      }
      if (e["propertyName"] == "ticket."+this.ticket.custom_field_name) {
        if (e["newValue"] != this.ticket.custom_field_value && this.ticket.custom_field_value != "") {
          //TODO set it back to this.ticket.custom_field_value
          setTicketCustomField(this.ticket.custom_field_name, this.ticket.custom_field_value).then((response) => {
            //console.log("Re-setTicketCustomField", response)
          })
        }
      }
    }, 500),

    ask_for_a_recording: function() {

      if (this.ask_recording_loading) {
        return;
      }

      if (this.ticket.isNew) {
        setTicketCustomField(this.ticket.custom_field_name, this.ticket.randomId).then((response) => {
          //console.log("setTicketCustomField", response)
        })
      }

      if (Object.keys(this.requester).length == 0 ) {
        console.warn("Zest need a ticket recipient")
        return
      }

      //console.log(this.product, this.location);
      //return

      var the_link = Spark.app_url.replace("https://app.hellozest.io", "https://"+this.team_slugname+".hellozest.io") + "/recorder";
      var zep = {
        s1:this.subdomain,
        s2:this.metadata.appId,
        s3:this.metadata.installationId,
        s4:this.ticket.id ? this.ticket.id : 0,
        s5:JSON.stringify(this.requester),
        s6:this.ticket.randomId
      }
      //console.log("requester",this.requester);
      //console.log("ticket",this.ticket);
      zep = btoa(JSON.stringify(zep));
      the_link += "?zep=" + encodeURI(zep);
      //the_link += "&rid=" + this.ticket.randomId;
      if (Spark.app_url.indexOf("hellozest.test") >= 0) {
        the_link += "&team="+this.team_slugname; // DEBUG
      }

      let the_image = Spark.app_url + "/img/rec.png";

      // var markdown = "[![Click here to record your screen]("+the_image+")]("+the_link+")";
      // markdown += "[Click here to record your screen]("+the_link+")";
      // let result = insertCommentMarkdown(markdown);
      // if (result && this.location == 'ticket_editor') {
      //   closePopup();
      // }
      // return;

      var result = null;

      if (this.ticket.comment.useRichText == true) {
        var html = '<p>';
        html += '<a href="' + the_link + '" title="Please record your screen">';
        html += '<img src="' + the_image + '" width="40" />';
        html += '</a>';
        //html += '<br />';
        html += '<a href="' + the_link + '" title="Please record your screen">';
        html += 'Click here to record your screen';
        html += '</a>';
        html += '</p>';
        result = insertCommentHtml(html);
      } else {
        var text = "Click on this link to record your screen: "+the_link;
        result = insertCommentText(text);
      }

      if (result && this.location == 'ticket_editor') {
        closePopup();
      }


    },
    send_a_recording: function(event)
    {
      if (!this.ticket.id) {
        event.preventDefault();
      }
      this.send_recording_loading = false;
    },
    start_auth: function() {
      if (!this.has_started_auth) {
        this.has_started_auth = true;
        this.get_ticket_fields();
      }
      return
    },
    get_ticket_fields: function() {
      getTicketFields().then((response) => {
        //console.log("getTicketFields", response)
        for(var i=0; i<response["ticketFields"].length; i++) {
          if (response["ticketFields"][i]["label"] == "zest_id") {
            this.ticket.custom_field_name = response["ticketFields"][i]["name"];
            this.ticket.custom_field_value = "";
            hideField(this.ticket.custom_field_name);
            break
          }
          //console.log(response["ticketFields"][i]["name"]);
        }
        if (this.ticket.custom_field_name) {
          getTicketField(this.ticket.custom_field_name).then((response) => {

            this.ticket.custom_field_value = response["ticket.customField:"+this.ticket.custom_field_name];
            //console.log("getTicketField value:", this.ticket.custom_field_value)
            this.get_current_user();
          });
        } else {
          this.get_current_user();
        }
      })
    },
    get_current_user: function() {
      getCurrentUser().then((currentUser) => {
        this.agent = currentUser.currentUser;//["currentUser"];
        //console.log("agent is ", this.agent)
        //this.agent_role = this.agent.role;
        this.get_requester();
      });
    },
    get_requester: function() {
      getTicketRequester().then((response) => {
        if (response["ticket.requester"]) {
          this.requester = response["ticket.requester"];
        } else {
          //console.log("NO requester");
        }
        // onTicketRequesterIdChanged(this.ticket_requester_changed());
        this.get_metadata();
      });
    },
    get_metadata: function() {
      getMetadata().then((metadata) => {
        this.metadata = metadata;
        //console.log("metadata",metadata)
        if (metadata.settings.team_slug) {
          this.has_zest_account = true;
          this.team_slug = metadata.settings.team_slug;
        }
        if (metadata.settings.integration) {
          this.has_zest_integration = true;
          this.integration_id = metadata.settings.integration;
        }
        if (this.has_zest_account && this.has_zest_integration) {
          this.is_registered = true;
        }
        //this.has_read_metadata = true;
        if (Object.keys(this.context).length == 0 ) {
          this.get_context();
        } /*else if (this.should_test_in_loop) {
          this.connection_test();
        }*/

      });
    },
    get_context: function() {
      getContext().then((context) => {
        //console.log("context",context)
        this.context = context;
        this.subdomain = context.account.subdomain;
        this.get_ticket_details();

        // existing = context.ticketId: 23
        // new = context.newTicketId: new/1
      });
    },
    get_ticket_details: function() {
      getTicketDetails().then((details) => {
        this.ticket.comment = details["ticket.comment"];
        this.ticket.brand = details["ticket.brand"];
        this.ticket.isNew = details["ticket.isNew"];
        this.ticket.randomId = "";
        this.ticket.id = 0;
        if (this.ticket.isNew) {
          this.ticket.randomId = this.randomId();
        }
        if (details["ticket.id"]) {
          this.ticket.id = details["ticket.id"];
        }
        // else {
        //   console.log("onTicketSubmitDone registered");
        //   onTicketSubmitDone(this.on_ticket_submit_done);
        // }
        //console.log(this.ticket);
        if (!this.is_registered) {
          this.connection_test();
        }
      });
      /*getTicketComment().then((comment) => {
        //console.log("comment",comment)
        this.ticket.comment = comment["ticket.comment"]
        getTicketBrand().then((brand) => {
          //console.log("brand",brand)
          this.ticket.brand = brand["ticket.brand"]
          getTicketIsNew().then((isNew) => {
            //console.log("isNew?",isNew)
            this.ticket.isNew = isNew["ticket.isNew"]
            this.ticket.randomId = this.randomId()
            if (this.ticket.isNew == false) {
              getTicketId().then((id) => {
                //console.log("ticket.id",id)
                this.ticket.id = id["ticket.id"]
                if (!this.is_registered) {
                  this.connection_test();
                }
              });
            } else {
              // if (this.location == 'ticket_editor') {
              //   onTicketSubmitDone(this.on_ticket_submit_done);
              //   //onTicketSave(this.on_ticket_save);
              // }
              if (!this.is_registered) {
                this.connection_test();
              }
            }
            //console.log("ticket",this.ticket);
          });
        });
      });*/
      /*getTicket().then((ticket) => {
        console.log(JSON.stringify(ticket))
        if (ticket.ticket) {
          this.ticket = ticket.ticket;

          if (ticket.ticket.brand) {
            if (ticket.ticket.brand.name) {
              this.brand_name = ticket.ticket.brand.name
            }
            if (ticket.ticket.brand.logo) {
              this.brand_logo = ticket.ticket.brand.logo.contentUrl
            }
          }
        }
        if (!this.is_registered) {
          this.connection_test();
        }
      });*/
    },
    randomId: function() {
      return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    },
    connectLinkClicked: function(e) {
      this.should_test_in_loop = true;
      this.connection_test();
    },
    connection_test: function() {

      if (this.is_registered)
      {
        //console.log("is_registered - no need to ask backend")
        this.should_test_in_loop = false;
        clearTimeout(this.test_loop);
        this.is_loading = false;
      }
      /*else if (this.team_slug == "" || this.team_slugname == "" || this.integration_id == 0)
      {
        getKey("team_slug").then(function(data) {
          this.team_slug = data;
          getKey("team_slugname").then(function(data2) {
            this.team_slugname = data2;
            getKey("integration_id").then(function(data3) {
              this.integration_id = data3;
              if (this.team_slug != "" || this.team_slugname != "" || this.integration_id != 0) {
                console.log("has stored data - no need to ask backend")
                this.has_zest_integration = true;
                this.has_zest_account = true;
                this.is_registered = true;
                this.is_loading = false;
                this.should_test_in_loop = false;
                if (this.location == "ticket_sidebar" && this.next_page != 0) {
                  this.get_videos_from_user();
                }
              }
            });
          });
        });
      }*/
      else
      {
        //console.log("ask backend")
        let url = Spark.app_url+'/zendeskapp/auth/'+this.subdomain;
        var params = {secret: this.secret, tid: this.ticket.id, rid: this.ticket.custom_field_value};
        axios.post(url, params)
          .then(response => {
            //console.log(response.data)
            if (response.data) {
              if (response.data.team_slug) {
                this.team_slug = response.data.team_slug;
                this.team_slugname = response.data.team_slugname;
                this.integration_id = response.data.integration;
                // setKey("team_slug", response.data.team_slug);
                // setKey("team_slugname", response.data.team_slugname);
                // setKey("integration_id", response.data.integration);
                this.has_zest_integration = true;
                this.has_zest_account = true;
                this.is_registered = true;
                this.is_loading = false;
                this.should_test_in_loop = false;
                if (this.location == "ticket_sidebar" && this.next_page != 0) {
                  this.get_videos_from_user();
                }
              }
            }
          })
          .catch(e => {
            //console.error(e);
            //this.has_match_error = true;
            this.is_loading = false;
            if (this.should_test_in_loop) {
              this.test_loop = setTimeout(function() {
                this.connection_test();
              }.bind(this), 5000);
            }
          });
      }
    },

    get_videos_from_user: function() {
      if (this.loading_videos) {
        return
      }
      let url = Spark.app_url+'/zendeskapp/get_videos';
      var params = {
        secret: this.secret,
        team_slug: this.team_slug,
        contact_email:this.requester.email,//"deja.shabazz@actionstep.com",//this.ticket_user.email,
        contact_id:this.requester.id,
        contact_external_id:this.requester.externalId,
        contact_name:this.requester.name,
        requester:this.requester,
        page: this.next_page,
        subdomain: this.subdomain,
      };
      // if (this.ticket_user.email) {
      //   params.contact_email = "deja.shabazz@actionstep.com"//this.ticket_user.email
      // }
      // if (this.ticket_user.id) {
      //   params.contact_id = this.ticket_user.id
      // }
      // if (this.ticket_user.externalId) {
      //   params.contact_externalId = this.ticket_user.externalId
      // }
      console.log("params:",params)
      this.loading_videos = true;
      axios.post(url, params)
        .then(response => {
          console.log(response.data);
          if (response.data) {
            if (response.data.data) {
              if (response.data.data.length > 0) {
                for (var i=0; i<response.data.data.length; i++) {
                  this.user_videos.push(response.data.data[i]);
                }
                if (this.next_page < response.data.to) {
                  this.next_page++;
                  //this.get_videos_from_user();
                } else {
                  this.next_page = 0;
                }
              }
            }
          }
          this.loading_videos = false;
        })
        .catch(e => {
          console.error(e);
          this.loading_videos = false;
        });
    }


  },

  computed: {
    agent_role: function() {
        if (this.agent) {
          return this.agent.role;
        }
        return "";
    },
    secret: function() {
      if (this.metadata) {
        if (this.metadata.appId && this.metadata.installationId) {
          return this.metadata.appId + "_" + this.metadata.installationId;
        }
      }
      return "";
    },
    login_url: function() {
      var zc = {
        subdomain:this.subdomain,
        appId:this.metadata.appId,
        installationId:this.metadata.installationId,
        agent_email:this.agent.email,
        agent_name: this.agent.name,
        agent_timezone: this.agent.timeZone.ianaName,
        brand_name:this.ticket.brand.name,
        brand_logo:this.ticket.brand.logo.contentUrl
        //assignee: this.assignee.user
      }
      zc = btoa(JSON.stringify(zc));
      return Spark.app_url+'/zendesk/install/'+zc
    },
    send_recording_url: function() {
      if (!this.ticket.id) {
        return "#";
      }
      if (this.ticket.id == 0) {
        return "#";
      }
      var the_link = Spark.app_url.replace("https://app.hellozest.io", "https://"+this.team_slugname+".hellozest.io") + "/recorder";
      var zid = {
        s1:this.subdomain,
        s2:this.metadata.appId,
        s3:this.metadata.installationId,
        s4:this.ticket.id,
        s6:JSON.stringify(this.agent),
        s5:JSON.stringify(this.requester)
      }
      //console.log("requester",this.requester);
      //console.log("ticket",this.ticket);
      zid = btoa(JSON.stringify(zid));
      the_link += "?zid=" + encodeURI(zid);
      //the_link += "&rid=" + this.ticket.randomId;
      if (Spark.app_url.indexOf("hellozest.test") >= 0) {
        the_link += "&team="+this.team_slugname; // DEBUG
      }
      return the_link;
    }
  }

}

</script>

<style scoped scss>


  .box {
    padding: 0;
    color: #2f3941;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
  }
  .box strong, .box a {
    color: #2f3941;
  }

  .box a.button,
  .box a.button:hover {
    color: #FFF;
    text-decoration: none;
  }

  .box.ticket_editor .preStuff {
    margin-top: 50px;
  }

  .stuffContainer {
    min-height: 100%;
    display: flex;
  }
  .stuffContainer > div {
    width: 100%;
  }


  .box.ticket_editor {
    background-position: center top;
    background-repeat: no-repeat;
    background-size: contain;
    background-image: url(/img/iframe.png)
  }

  .sidebarlist a,
  .editor a {
    color: #2F3941;
    display: block;
    text-decoration: none;
    font-size: 0.75rem;
    padding: 8px 0 8px 8px;
    border-bottom: 1px solid rgb(233, 235, 237);
  }
  .editor a {
   height: 60px;
   display: flex;
   justify-content: center;
   flex-direction: column;
  }
  .sidebarlist a:hover,
  .editor a:hover {
    background: #F8F9F9;
  }
  .sidebarlist a:last-child,
  .editor a:last-child {
    border-bottom: 1px solid transparent;
  }
  .sidebarlist a .block,
  .editor a .block {
    margin: 0;
    padding: 0;
  }

  .block.title {
    font-size: 0.8rem;
    padding-left: 8px;
  }
  .block.novideo {
    font-size: 0.75rem;
  }

  a.action, a.action:hover, a.action:active,
  div.no_requester {
    padding-left: 43px;
    background-position: 6px center;
    background-repeat: no-repeat;
    background-size: auto 30px;
  }
  a.action:hover {
    background-repeat: no-repeat;
  }
  a.action.ask {
    background-image:url('/img/intercom_button_ask.png');
  }
  a.action.record {
    background-image:url('/img/intercom_button_send.png');
  }

  a.action.help {
    color: #425DF2;
    padding-right: 8px;
    background: none;
  }
  a.action.help strong {
    color: #425DF2;
  }
  a.action.help:hover {
    background-color: transparent;
  }
  a.action.help:hover strong {
    color: #244DE1;
  }
  .editorPopTitle {
    color: #2F3941;
    position: absolute;
    top: 10px;
    left: 19px;
    font-weight: bold;
    font-size: 15px;
  }

  .editor a.action, .editor a.action:hover, .editor a.action:active {
    background-position: 11px center;
  }

</style>


