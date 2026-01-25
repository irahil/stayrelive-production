
(function($, Drupal, once) {
  'use strict';

  Drupal.behaviors.vanillaAutocomplete = {
    attach: function(context, settings) {

      $(once('vanillaAutocomplete', '#autocomplete_town_city', context)).each(function() {
        var input = this;

      fetch('/city-autocomplete')
        .then(response => response.json())
        .then(cities => {
          initAutocomplete(input, cities);
        });
      });

      function initAutocomplete(inp, arr) {
        var currentFocus;
        
        inp.addEventListener("input", function(e) {
          var a, b, i, val = this.value;
          closeAllLists();
          if (!val) { return false; }
          currentFocus = -1;
          
          a = document.createElement("DIV");
          a.setAttribute("id", this.id + "autocomplete-list");
          a.setAttribute("class", "autocomplete-items");
          this.parentNode.appendChild(a);
          
          var hasMatches = false;
          
          for (i = 0; i < arr.length; i++) {
            if (arr[i].substr(0, val.length).toUpperCase() === val.toUpperCase()) {
              hasMatches = true;
              b = document.createElement("DIV");
              b.innerHTML = "<strong>" + arr[i].substr(0, val.length) + "</strong>";
              b.innerHTML += arr[i].substr(val.length);
              b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
              
              b.addEventListener("click", function(e) {
                inp.value = this.getElementsByTagName("input")[0].value;
                closeAllLists();
                $(inp).trigger('change');
              });
              
              a.appendChild(b);
            }
          }
          
          if (!hasMatches) {
            b = document.createElement("DIV");
            b.innerHTML = "Not found";
            a.appendChild(b);
          }
        });

        inp.addEventListener("keydown", function(e) {
          var x = document.getElementById(this.id + "autocomplete-list");
          if (x) x = x.getElementsByTagName("div");
          
          if (e.key === "ArrowDown") {
            currentFocus++;
            addActive(x);
          } 
          else if (e.key === "ArrowUp") {
            currentFocus--;
            addActive(x);
          } 
          else if (e.key === "Enter") {
            e.preventDefault();
            if (currentFocus > -1 && x) {
              if (!x[currentFocus].classList.contains("no-results")) {
                x[currentFocus].click();
              }
            }
          }
        });

        function addActive(x) {
          if (!x) return false;
          removeActive(x);
          
          if (currentFocus >= x.length) currentFocus = 0;
          if (currentFocus < 0) currentFocus = (x.length - 1);
          
          if (!x[currentFocus].classList.contains("no-results")) {
            x[currentFocus].classList.add("autocomplete-active");
          }
        }

        function removeActive(x) {
          for (var i = 0; i < x.length; i++) {
            x[i].classList.remove("autocomplete-active");
          }
        }

        function closeAllLists(elmnt) {
          var x = document.getElementsByClassName("autocomplete-items");
          for (var i = 0; i < x.length; i++) {
            if (elmnt != x[i] && elmnt != inp) {
              x[i].parentNode.removeChild(x[i]);
            }
          }
        }

        document.addEventListener("click", function(e) {
          closeAllLists(e.target);
        });
      }
    }
  };
})(jQuery, Drupal, once);
