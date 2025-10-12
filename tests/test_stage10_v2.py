import os
import subprocess
import unittest
from pathlib import Path
from unittest.mock import patch

from python import stage10_v2


class Stage10V2Tests(unittest.TestCase):
    def test_get_wp_bin_directory_error(self) -> None:
        with patch.dict(os.environ, {"WP_BIN": "/tmp"}):
            with self.assertRaises(stage10_v2.Stage10Error):
                stage10_v2.get_wp_bin()

    def test_build_wp_args_adds_path_when_missing(self) -> None:
        with patch.dict(os.environ, {"WP_PATH_ARGS": "--skip-themes"}):
            args = stage10_v2.build_wp_args(Path("/var/www"))
        self.assertIn("--skip-themes", args)
        self.assertIn("--path=/var/www", args)

    def test_run_fast_parses_last_json_line(self) -> None:
        payload = {"sku": "ABC123"}
        stdout = "Notice: something\n{\"sku\":\"ABC123\",\"actions\":[\"updated\"],\"errors\":[]}\n"
        completed = subprocess.CompletedProcess(
            args=[],
            returncode=0,
            stdout=stdout,
            stderr=""
        )
        with patch.dict(os.environ, {"DRY_RUN": "0"}, clear=False):
            with patch("python.stage10_v2.get_wp_bin", return_value="wp"):
                with patch("python.stage10_v2.build_wp_args", return_value=["--path=/wp"]):
                    with patch("python.stage10_v2.subprocess.run", return_value=completed) as fake_run:
                        result = stage10_v2.run_fast(payload, "/tmp/fast.php", Path("/wp"))
        self.assertEqual(result["sku"], "ABC123")
        self.assertIn("updated", result["actions"])
        fake_run.assert_called_once()
        cmd = fake_run.call_args[0][0]
        self.assertEqual(cmd[0], "wp")
        self.assertIn("--no-color", cmd)
        self.assertIn("eval-file", cmd)

    def test_run_fast_raises_when_no_json(self) -> None:
        completed = subprocess.CompletedProcess(
            args=[],
            returncode=1,
            stdout="warning\n",
            stderr="fatal error"
        )
        with patch.dict(os.environ, {"DRY_RUN": "0"}, clear=False):
            with patch("python.stage10_v2.get_wp_bin", return_value="wp"):
                with patch("python.stage10_v2.build_wp_args", return_value=["--path=/wp"]):
                    with patch("python.stage10_v2.subprocess.run", return_value=completed):
                        with self.assertRaises(stage10_v2.WPCLIError) as ctx:
                            stage10_v2.run_fast({}, "/tmp/fast.php", Path("/wp"))
        self.assertIn("wp_cli_failed", str(ctx.exception))


if __name__ == "__main__":
    unittest.main()
