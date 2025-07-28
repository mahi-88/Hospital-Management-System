@extends($layout)

@section('content')

    <x-global::pageheader :icon="'fa fa-puzzle-piece'">
        <h1>App Marketplace</h1>
    </x-global::pageheader>

    @displayNotification()

    <div class="maincontent">

        @include('plugins::partials.plugintabs',  ["currentUrl" => "marketplace"])

       <div class="maincontentinner">

           <div class="tw-w-full"
                hx-get="{{ BASE_URL }}/hx/plugins/marketplaceplugins/getlist"
                hx-trigger="load"
                hx-target="#pluginList"
                hx-indicator=".htmx-indicator, .htmx-loaded-content"
                hx-swap="outerHTML"
           >
               <div id="pluginList">
                   <div class="htmx-indicator tw-ml-m tw-mr-m tw-pt-l">
                       <x-global::loadingText type="plugincard" count="5" includeHeadline="false"/>
                   </div>
               </div>
           </div>

       </div>

    </div>

@endsection
