
@extends('spark::layouts.zendesk')
@section('content')
  <integration-zendesk product="{{$product}}" location="{{$location}}"></integration-zendesk>
@endsection



