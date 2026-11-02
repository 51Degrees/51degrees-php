# Client Hints to HTTP headers

51Degrees device detection requires User Agent Client Hints evidence to be provided 
in the HTTP headers format, similar to how the browser would send them if requested by the server 
via the Accept-CH or Delegate-CH header.

This example code converts User Agent Client Hints obtained through the 
getHighEntropyValues() browser API into HTTP headers format.


## Notes

- **Note 1**: `Sec-CH-UA-Arch`, `Sec-CH-UA-Bitness`, `Sec-CH-UA-Model`, `Sec-CH-UA-Platform`, `Sec-CH-UA-Platform-Version` headers
are of type `sf-string` according to the [WICG spec](https://wicg.github.io/ua-client-hints/#sec-ch-ua-arch). This means the values
must be enclosed in quotes (except when the value is empty).

- **Note 2**: the `Sec-CH-UA` and `Sec-CH-UA-Full-Version-List` are list headers so a single space after the comma is added. The `sf-list` 
mentions it is an OWS (optional whitespace), but we found it widely practiced in the industry and it also improves readability of the header.

- **Note 3**: Synthesis of `Sec-CH-UA-Full-Version` header is excluded as it is deprecated.

## Example

The following json can be obtained by running:
```js
console.log(
	JSON.stringify(
		await navigator.userAgentData.getHighEntropyValues([
			'architecture', 'bitness', 'model', 'platformVersion', 'fullVersionList'
			]
		)
	)
)
```

```json
{
    "architecture": "x86",
    "bitness": "64",
    "brands":
    [
        {
            "brand": "Chromium",
            "version": "116"
        },
        {
            "brand": "Not)A;Brand",
            "version": "24"
        },
        {
            "brand": "Google Chrome",
            "version": "116"
        }
    ],
    "fullVersionList":
    [
        {
            "brand": "Chromium",
            "version": "116.0.5845.187"
        },
        {
            "brand": "Not)A;Brand",
            "version": "24.0.0.0"
        },
        {
            "brand": "Google Chrome",
            "version": "116.0.5845.187"
        }
    ],
    "mobile": false,
    "model": "",
    "platform": "macOS",
    "platformVersion": "13.5.2"
}
```

It converts into these headers:

```http
Sec-CH-UA: "Chromium";v="116", "Not)A;Brand";v="24", "Google Chrome";v="116"
Sec-CH-UA-Arch: "x86"
Sec-CH-UA-Bitness: "64"
Sec-CH-UA-Full-Version-List: "Chromium";v="116.0.5845.187", "Not)A;Brand";v="24.0.0.0", "Google Chrome";v="116.0.5845.187"
Sec-CH-UA-Mobile: ?0
Sec-CH-UA-Model: 
Sec-CH-UA-Platform: "macOS"
Sec-CH-UA-Platform-Version: "13.5.2"
```

