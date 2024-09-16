document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("form");
    const searchInput = document.getElementById("search");
    const errorMessage = document.getElementById("error-message");
    const markInstance = new Mark(document.querySelectorAll("section, div"));
  
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      const searchTerm = searchInput.value.toLowerCase();
  
      markInstance.unmark();
      markInstance.mark(searchTerm, {
        separateWordSearch: false,
        diacritics: false,
        done: function (count) {
          if (count > 0) {
            errorMessage.style.display = "none";
            scrollToFirstSearchResult();
          } else {
            errorMessage.style.display = "block";
          }
        },
      });
    });
  
    function scrollToFirstSearchResult() {
      const firstHighlightedElement = document.querySelector("mark");
      if (firstHighlightedElement) {
        firstHighlightedElement.scrollIntoView({ behavior: "smooth" });
    }
    }
  });