"""Runs request.php for each tree and number of names, turning the order of
the trees round by one each round, and prints every figure, the medians and
the full ranges.

Usage: python3 run.py <extension> <data file> <IP address> <name>=<tree>...
"""
import json
import statistics
import subprocess
import sys

extension, data_file, ip = sys.argv[1:4]
trees = dict(arg.split("=", 1) for arg in sys.argv[4:])
SCRIPT = __file__.replace("run.py", "request.php")
ORDER = list(trees)


def php(names, *args):
    command = ["php", "-d", f"extension={extension}",
               "-d", f"FiftyOneDegreesIpIntelligenceEngine.data_file={data_file}"]
    if names:
        command += ["-d", "FiftyOneDegreesIpIntelligenceEngine."
                    f"required_properties={names}"]
    run = subprocess.run(command + [SCRIPT, *args],
                         capture_output=True, text=True)
    if run.returncode != 0:
        print(run.stdout[-3000:], run.stderr[-3000:], flush=True)
        sys.exit(f"php exited {run.returncode} for {args[:3]}")
    return json.loads(run.stdout.strip().splitlines()[-1])


listed = php("", "list", trees[ORDER[0]])
print("The names an engine built with no names gives:", json.dumps(listed))
NAMES = [p["name"] for p in listed if p["available"]]
SIZES = sorted({s for s in (1, 5, 15, len(NAMES)) if s <= len(NAMES)})
print(f"{len(NAMES)} available names, timed with the first {SIZES}")
print()


def measure(title, mode, rounds, count, unit):
    results = {(size, tree): [] for size in SIZES for tree in trees}
    values = {}
    for round_number in range(rounds):
        shift = round_number % len(ORDER)
        order = ORDER[shift:] + ORDER[:shift]
        for size in SIZES:
            names = ",".join(NAMES[:size])
            for tree in order:
                result = php(names, mode, trees[tree], names, str(count), ip)
                results[(size, tree)].append(result["ns"])
                values.setdefault(size, {})[tree] = result["values"]
                print(f"{title} round {round_number + 1} names {size} {tree} "
                      f"{result['ns']:,.1f} ns {unit}", flush=True)

    print()
    print(f"{title}, {count} a figure, {rounds} rounds, ns {unit}, "
          "median (lowest to highest)")
    print()
    print("| Names | " + " | ".join(trees) + " |")
    print("| --- |" + " --- |" * len(trees))
    for size in SIZES:
        first = values[size][ORDER[0]]
        for tree in trees:
            if values[size][tree] != first:
                sys.exit(f"{tree} read other values with {size} names: "
                         f"{values[size][tree]} against {first}")
        cells = []
        for tree in trees:
            figures = results[(size, tree)]
            cells.append(f"{statistics.median(figures):,.1f} "
                         f"({min(figures):,.1f} to {max(figures):,.1f})")
        print(f"| {size} | " + " | ".join(cells) + " |")
    print()
    for size in SIZES:
        for a in trees:
            for b in trees:
                if a != b:
                    wins = sum(x < y for x, y in zip(
                        results[(size, a)], results[(size, b)]))
                    print(f"names {size}: {a} faster than {b} in {wins} of "
                          f"{rounds} rounds")
                    if max(results[(size, a)]) < min(results[(size, b)]):
                        print(f"names {size}: the whole range of {a} is "
                              f"below that of {b}")
    print()
    print("Values, the same in every tree:", json.dumps(values))
    print()


measure("Whole request", "request", 10, 3000, "a request")
measure("Reads alone", "reads", 10, 20000, "a read")
