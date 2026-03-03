document.getElementById("tipo_usuario").addEventListener("change",function () {
    document.getElementById("campos_alumno").style.display = "none";
    document.getElementById("campos_docente").style.display = "none";
    document.getElementById("campos_empresa").style.display = "none";

    if (this.value === "alumno") {
        document.getElementById("campos_alumno").style.display == "block";
    }
    else if (this.value === "docente") {
        document.getElementById("campos_docente").style.display == "block";
    }
    else if (this.value === "empresa") {
        document.getElementById("campos_empresa").style.display == "block";
    }
});