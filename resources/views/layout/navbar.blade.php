 <nav class="navbar navbar-expand-lg">
     <div class="container align-content-between">
         <!-- Logo on the left -->
         <div class="ml-3 d-md-none d-lg-block">
             <a class="navbar-brand" href="#">
                 <img src="{{ asset('images/logo.png') }}" alt="Your Logo" width="180" height="70">
             </a>
         </div>

         <!-- Right-aligned content (datetime) -->
         <div class="mr-4 d-md-none d-lg-block">
             <div class="row">
                 <h4 class="mr-4 mt-2"> <!-- Adjust font-size as needed -->
                     <?php
                     // Set the locale to Indonesian
                     setlocale(LC_TIME, 'id_ID');
                     
                     // Get the current timestamp (you can use a specific timestamp instead)
                     $timestamp = time();
                     
                     // Format the date
                     $formattedDate = strftime('%A, %d %b %Y', $timestamp);
                     
                     // Output the formatted date
                     echo $formattedDate; // Outputs something like: Rabu, 23 Sep 2023
                     ?>
                 </h4>
                 <h2> <!-- Adjust font-size as needed -->
                     <?php
                     // Set the locale to Indonesian
                     setlocale(LC_TIME, 'id_ID');
                     
                     // Get the current timestamp (you can use a specific timestamp instead)
                     $timestamp = time();
                     
                     // Format the time
                     $formattedTime = strftime('%H:%M', $timestamp);
                     
                     // Output the formatted time
                     echo $formattedTime; // Outputs something like: 13:10
                     ?>
                 </h2>
             </div>
         </div>

     </div>
 </nav>
