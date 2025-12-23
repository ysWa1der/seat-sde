{{-- Override Fuzzwork URL with CCP official URL --}}
@section('page_script')
  @parent
  <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
      // Find and replace Fuzzwork URL with CCP official URL
      const links = document.querySelectorAll('a[href*="fuzzwork.co.uk/dump"]');
      links.forEach(link => {
        link.href = 'https://developers.eveonline.com/static-data';
        link.textContent = 'https://developers.eveonline.com/static-data';
      });
    });
  </script>
@endsection
