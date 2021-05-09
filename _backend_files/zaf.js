import ZAFClient from 'zendesk_app_framework_sdk'
const zafClient = ZAFClient.init()

export const resize = () => {
  zafClient.invoke('resize', { width: '100%', height: '400px' })
}

export const getTicket = () => {
  return zafClient.get('ticket').then((response) => {
    return {ticket: response.ticket}
  }, (response) => {
    return `Error from zafClient ticket status: ${response.status}`
  })
}
export const getMetadata = () => {
  return zafClient.metadata().then(function(metadata) {
    return metadata
  });
}
export const getContext = () => {
  return zafClient.context().then(function(context) {
    return context
  });
}
export const getCurrentUser = () => {
  return zafClient.get('currentUser').then(function(currentUser) {
    return currentUser
  });
}
// export const getCurrentAccount = () => {
//   return zafClient.get('currentAccount').then(function(currentAccount) {
//     return currentAccount
//   });
// }
export const getTicketRequester = () => {
  return zafClient.get('ticket.requester').then(function(response) {
    return response
  });
}
export const getTicketComment = () => {
  return zafClient.get('ticket.comment').then(function(response) {
    return response
  });
}
export const getTicketId = () => {
  return zafClient.get('ticket.id').then(function(response) {
    return response
  });
}
export const getTicketIsNew = () => {
  return zafClient.get('ticket.isNew').then(function(response) {
    return response
  });
}
export const getTicketBrand = () => {
  return zafClient.get('ticket.brand').then(function(response) {
    return response
  });
}
export const getTicketDetails = () => {
  return zafClient.get(['ticket.brand','ticket.comment','ticket.id','ticket.isNew']).then(function(response) {
    return response
  });
}
export const getTicketFields = () => {
  return zafClient.get('ticketFields').then(function(response) {
    return response
  });
}
export const getTicketField = (name) => {
  return zafClient.get('ticket.customField:'+name).then(function(response) {
    return response
  });
}




export const closePopup = () => {
  return zafClient.invoke('app.close');
}
export const hideField = (identifier) => {
  return zafClient.invoke('ticketFields:'+identifier+'.hide');
}




export const insertCommentHtml = (html) => {
  return zafClient.invoke('comment.appendHtml', html);
}
export const insertCommentMarkdown = (markdown) => {
  return zafClient.invoke('comment.appendMarkdown', markdown);
}
export const insertCommentText = (text) => {
  return zafClient.invoke('comment.appendText', text);
}
export const editorInsertHtml = (html) => {
  return zafClient.invoke('ticket.editor.insert', html);
}
export const editorInsertLink = () => {
  return zafClient.invoke('ticket.editor.hyperlink');
}
export const editorInsertImage = (image_url) => {
  return zafClient.invoke('ticket.editor.inlineImage', image_url);
}

export const setTicketCustomField = (name,value) => {
  return zafClient.set('ticket.customField:'+name, value);
}




export const setKey = (key, val) => {
  return zafClient.metadata().then(function(metadata) {
    return localStorage.setItem(metadata.installationId + ":" + key, val);
  });
}

export const getKey = (key) => {
  return zafClient.metadata().then(function(metadata) {
    return localStorage.getItem(metadata.installationId + ":" + key);
  });
}




export const onAppExpanded = (callback) => {
  zafClient.on('app.expanded', function() {
    return callback;
  });
}
export const onAppActivated = (callback) => {
  zafClient.on('app.activated', function() {
    return callback;
  });
}
export const onPaneActivated = (callback) => {
  zafClient.on('pane.activated', function() {
    return callback;
  });
}
export const onTicketChanged = (callback) => {
  zafClient.on('*.changed', function(e) {
    return callback(e);
  });
}
export const onTicketSubmitDone = (callback) => {
  zafClient.on('ticket.submit.done', function(e) {
    return zafClient.on('ticket.save', callback);
    //return callback(e);
  });
}
export const onTicketSave = (callback) => {
  zafClient.on('ticket.save', callback);
}
export const onTicketSubmitStart = (callback) => {
  zafClient.on('ticket.submit.start', function(e) {
    return callback(e);
  });
}



//console.log("ZAF Loaded")
