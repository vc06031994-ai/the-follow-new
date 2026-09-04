document.addEventListener('DOMContentLoaded', function() {
    const syllabusHeaders = document.querySelectorAll('.tfp-syllabus-header');
    
    syllabusHeaders.forEach(header => {
        header.addEventListener('click', () => {
            const currentItem = header.parentElement;
            const isActive = currentItem.classList.contains('is-active');
            
            // Toggle others off
            document.querySelectorAll('.tfp-syllabus-item').forEach(item => {
                item.classList.remove('is-active');
            });
            
            if (!isActive) {
                currentItem.classList.add('is-active');
            }
        });
    });
});
