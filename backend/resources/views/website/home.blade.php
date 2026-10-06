@extends('layouts.website')

@section('content')
{{-- Sections in the order set in Website / CMS → Page Sections; hidden ones are left out. --}}
@foreach ($homeSections as $section)
    @include('website.home.'.$section)
@endforeach
<div class="h-16"></div>
@endsection
